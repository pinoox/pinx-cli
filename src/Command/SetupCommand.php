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
    description: 'One-shot project setup: deps, migrations, seeders, and patches',
)]
final class SetupCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation')
            ->addOption('db-only', null, InputOption::VALUE_NONE, 'Only migrations, seeders, and patches')
            ->addOption('skip-deps', null, InputOption::VALUE_NONE, 'Skip Composer / npm dependency install')
            ->addOption('skip-frontend', null, InputOption::VALUE_NONE, 'Skip npm (Composer only during deps)')
            ->addOption('skip-seed', null, InputOption::VALUE_NONE, 'Skip database seeders')
            ->addOption('skip-patch', null, InputOption::VALUE_NONE, 'Skip data patches')
            ->setHelp(
                <<<'HELP'
Prepare a local app in one command:

  1. Composer + npm dependencies (deps:install, all themes)
  2. Platform migrations
  3. App migrations
  4. Platform seeders
  5. App seeders
  6. Platform patches
  7. App patches

Examples:
  pinx setup
  pinx setup --yes
  pinx setup --db-only --yes
  pinx setup --skip-deps --yes
  pinx setup --skip-frontend --yes
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

        $dbOnly = (bool) $input->getOption('db-only');
        $runDeps = !$dbOnly && !(bool) $input->getOption('skip-deps');
        $composerOnly = (bool) $input->getOption('skip-frontend');
        $runSeed = !(bool) $input->getOption('skip-seed');
        $runPatch = !(bool) $input->getOption('skip-patch');

        $plan = [];
        if ($runDeps) {
            $plan[] = $composerOnly ? 'Composer dependencies' : 'Composer + npm dependencies';
        }
        $plan[] = 'Platform + app migrations';
        if ($runSeed) {
            $plan[] = 'Platform + app seeders';
        }
        if ($runPatch) {
            $plan[] = 'Platform + app patches';
        }

        $io->title('Pinx Setup');
        $io->definitionList(
            ['Package' => $package],
            ['Steps' => implode(' → ', $plan)],
        );

        if (!$input->getOption('yes') && !$io->confirm('Run project setup for ' . $package . '?', true)) {
            $io->warning('Setup cancelled.');

            return Command::SUCCESS;
        }

        $runner = new PincoreRunner($root);
        $platform = 'platform';

        if ($runDeps) {
            $io->section('Dependencies');
            $depsArgs = ['deps', 'install', $package, '--all-themes', '-n', '--no-ansi'];
            if ($composerOnly) {
                $depsArgs[] = '--composer-only';
            }
            if ($runner->run($depsArgs, $output) !== 0) {
                $io->error('Dependency install failed.');

                return Command::FAILURE;
            }
        }

        $io->section('Platform migrations');
        if ($runner->run(['migrate', $platform, '-n'], $output) !== 0) {
            return Command::FAILURE;
        }

        $io->section('App migrations');
        if ($runner->run(['migrate', $package, '-n'], $output) !== 0) {
            return Command::FAILURE;
        }

        if ($runSeed) {
            $io->section('Platform seeders');
            if ($runner->run(['seed', $platform, '-n'], $output) !== 0) {
                return Command::FAILURE;
            }

            $io->section('App seeders');
            if ($runner->run(['seed', $package, '-n'], $output) !== 0) {
                return Command::FAILURE;
            }
        }

        if ($runPatch) {
            $io->section('Platform patches');
            if ($runner->run(['patch', $platform], $output) !== 0) {
                return Command::FAILURE;
            }

            $io->section('App patches');
            if ($runner->run(['patch', $package], $output) !== 0) {
                return Command::FAILURE;
            }
        }

        $io->success('Setup complete.');

        return Command::SUCCESS;
    }
}
