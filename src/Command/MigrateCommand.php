<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\DevApp;
use Pinoox\PinxCli\Support\PincoreRunner;
use Pinoox\PinxCli\Support\ProjectRoot;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'migrate',
    description: 'Run database migrations for the current app (and optionally platform)',
)]
final class MigrateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('platform', null, InputOption::VALUE_NONE, 'Also migrate platform tables first')
            ->addOption('rollback', null, InputOption::VALUE_NONE, 'Rollback the last batch')
            ->addOption('status', null, InputOption::VALUE_NONE, 'Show migration status');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $root = ProjectRoot::require();
            $package = DevApp::requirePackage($root);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $runner = new PincoreRunner($root);

        if ($input->getOption('status')) {
            $code = $runner->run(['migrate:status', $package], $output);

            return $code === 0 ? Command::SUCCESS : Command::FAILURE;
        }

        if ($input->getOption('rollback')) {
            $code = $runner->run(['migrate:rollback', $package, '-n'], $output);

            return $code === 0 ? Command::SUCCESS : Command::FAILURE;
        }

        if ($input->getOption('platform')) {
            if ($runner->run(['migrate', 'platform', '-n'], $output) !== 0) {
                return Command::FAILURE;
            }
        }

        return $runner->run(['migrate', $package, '-n'], $output) === 0
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}
