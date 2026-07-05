<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\AppContext;
use Pinoox\PinxCli\Support\ComposerRunner;
use Pinoox\PinxCli\Support\Doctor\DoctorRunner;
use Pinoox\PinxCli\Support\ProjectRoot;
use Pinoox\PinxCli\Support\RepairFinding;
use Pinoox\PinxCli\Support\SingleAppRepairer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'repair',
    description: 'Diagnose and repair a Pinx single-app project',
)]
final class RepairCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('package', 'p', InputOption::VALUE_REQUIRED, 'App package name when it cannot be detected')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'App display name for generated files')
            ->addOption('developer', null, InputOption::VALUE_REQUIRED, 'Developer name for generated files')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing Pinx support files')
            ->addOption('install', null, InputOption::VALUE_NONE, 'Run composer install after repairing files')
            ->addOption('skip-doctor', null, InputOption::VALUE_NONE, 'Skip the final doctor check')
            ->setHelp(
                <<<'HELP'
Detects missing support files, runtime folders, registry mapping, route files,
and broken action routing setup, then fixes only what is actually needed.

Examples:
  pinx repair
  pinx repair --install
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $root = ProjectRoot::normalize(getcwd() ?: '.');

        try {
            $result = (new SingleAppRepairer())->repair(
                projectRoot: $root,
                package: (string) ($input->getOption('package') ?: ''),
                displayName: (string) ($input->getOption('name') ?: ''),
                developer: (string) ($input->getOption('developer') ?: ''),
                overwrite: (bool) $input->getOption('force'),
                output: $output,
            );
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $findings = $result['findings'];
        $changed = $result['changed'];
        $remaining = $result['remaining'];

        if ($findings !== []) {
            $io->section('Issues detected');
            $io->listing(array_map(
                static fn (RepairFinding $finding): string => $finding->label . ' — ' . $finding->detail,
                $findings,
            ));
        } else {
            $io->note('No repair issues were detected.');
        }

        if ($changed === []) {
            $io->success('No repair changes were needed.');
        } else {
            $io->success('Repair complete.');
            $io->section('Fixed');
            $io->listing($changed);
        }

        if ($remaining !== []) {
            $io->warning('Some issues could not be repaired automatically:');
            $io->listing(array_map(
                static fn (RepairFinding $finding): string => $finding->label . ' — ' . $finding->detail,
                $remaining,
            ));
        }

        if ($input->getOption('install')) {
            $io->section('Installing dependencies');
            if (ComposerRunner::run(['install', '--no-interaction'], $root, $output) !== 0) {
                return Command::FAILURE;
            }
        }

        if (!$input->getOption('skip-doctor')) {
            $context = AppContext::find($root);
            $report = (new DoctorRunner())->run($context);

            if ($report->failCount() > 0) {
                $io->warning('Repair finished, but doctor still reports blocking issues. Run pinx doctor for details.');

                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }
}
