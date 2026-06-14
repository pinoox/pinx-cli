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
    description: 'Prepare database: platform + app migrations, seeders, and patches',
)]
final class SetupCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation')
            ->setHelp(
                <<<'HELP'
One-shot database preparation for the current app:

  1. Platform migrations
  2. App migrations
  3. Platform seeders
  4. App seeders
  5. Platform patches
  6. App patches

Examples:
  pinx setup
  pinx setup --yes
HELP
            );
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

        if (!$input->getOption('yes') && !$io->confirm('Run migrations, seeders, and patches for ' . $package . '?', true)) {
            return Command::SUCCESS;
        }

        $runner = new PincoreRunner($root);
        $platform = 'platform';

        $io->section('Platform migrations');
        if ($runner->run(['migrate', $platform, '-n'], $output) !== 0) {
            return Command::FAILURE;
        }

        $io->section('App migrations');
        if ($runner->run(['migrate', $package, '-n'], $output) !== 0) {
            return Command::FAILURE;
        }

        $io->section('Platform seeders');
        if ($runner->run(['seed', $platform, '-n'], $output) !== 0) {
            return Command::FAILURE;
        }

        $io->section('App seeders');
        if ($runner->run(['seed', $package, '-n'], $output) !== 0) {
            return Command::FAILURE;
        }

        $io->section('Platform patches');
        if ($runner->run(['patch', $platform], $output) !== 0) {
            return Command::FAILURE;
        }

        $io->section('App patches');
        if ($runner->run(['patch', $package], $output) !== 0) {
            return Command::FAILURE;
        }

        $io->success('Setup complete.');

        return Command::SUCCESS;
    }
}
