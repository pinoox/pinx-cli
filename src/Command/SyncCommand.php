<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\ProjectRoot;
use Pinoox\PinxCli\Support\SingleAppRepairer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'sync',
    description: 'Sync single-app Pinx support files from the pinoox/app template',
)]
final class SyncCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('package', 'p', InputOption::VALUE_REQUIRED, 'App package name when it cannot be detected')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'App display name for generated files')
            ->addOption('developer', null, InputOption::VALUE_REQUIRED, 'Developer name for generated files')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing template-managed support files');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $root = ProjectRoot::normalize(getcwd() ?: '.');

        try {
            $changed = (new SingleAppRepairer())->sync(
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

        if ($changed === []) {
            $io->success('Single-app files are already in sync.');

            return Command::SUCCESS;
        }

        $io->success('Single-app files synced.');
        $io->listing($changed);

        return Command::SUCCESS;
    }
}
