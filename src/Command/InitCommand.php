<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\ComposerRunner;
use Pinoox\PinxCli\Support\ProjectRoot;
use Pinoox\PinxCli\Support\ProjectScaffolder;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'init',
    description: 'Initialize the current directory as a single-app Pinoox project (root app layout)',
)]
final class InitCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('package', 'p', InputOption::VALUE_REQUIRED, 'App package name (com_vendor_app)')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'App display name')
            ->addOption('developer', null, InputOption::VALUE_REQUIRED, 'Developer name')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Continue even when app.php already exists');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $root = ProjectRoot::normalize(getcwd() ?: '.');

        if (is_file($root . '/app.php') && !$input->getOption('force')) {
            $io->error('app.php already exists. Use --force to scaffold missing files anyway.');

            return Command::FAILURE;
        }

        $packageInput = (string) ($input->getOption('package') ?: '');
        if ($packageInput === '') {
            $packageInput = (string) $io->ask('Package name', 'com_my_app');
        }

        try {
            $package = ProjectScaffolder::normalizePackage($packageInput);
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $displayName = (string) ($input->getOption('name') ?: ProjectScaffolder::displayNameFromPackage($package));
        $developer = (string) ($input->getOption('developer') ?: 'Developer');
        $replacements = ProjectScaffolder::defaultReplacements($package, $displayName, $developer);

        if (!is_file($root . '/composer.json')) {
            (new ProjectScaffolder())->copyFileFromSkeleton('composer.json', $root . '/composer.json', $replacements);
        }

        (new ProjectScaffolder())->initInPlace($root, $replacements);

        if (!is_dir($root . '/pincore') && !is_dir($root . '/vendor/pinoox/pincore')) {
            $io->section('Installing dependencies');
            $code = ComposerRunner::run(['install', '--no-interaction'], $root, $output);

            if ($code !== 0) {
                return Command::FAILURE;
            }
        }

        $io->success('Project initialized.');
        $io->text('Next: cp .env.example .env && pinx setup && pinx dev');

        return Command::SUCCESS;
    }
}
