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
    name: 'setup',
    description: 'Prepare database: migrate platform + app, then run seeders',
)]
final class SetupCommand extends Command
{
    protected function configure(): void
    {
        $this->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation');
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

        if (!$input->getOption('yes') && !$io->confirm('Run platform + app migrations for ' . $package . '?', true)) {
            return Command::SUCCESS;
        }

        $runner = new PincoreRunner($root);

        $io->section('Platform migrations');
        if ($runner->run(['migrate', 'platform', '-n'], $output) !== 0) {
            return Command::FAILURE;
        }

        $io->section('App migrations');
        if ($runner->run(['migrate', $package, '-n'], $output) !== 0) {
            return Command::FAILURE;
        }

        $io->section('Seeders');
        $runner->run(['seed', $package, '-n'], $output);

        $io->success('Setup complete.');

        return Command::SUCCESS;
    }
}
