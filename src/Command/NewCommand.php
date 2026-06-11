<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\ComposerRunner;
use Pinoox\PinxCli\Support\ProjectRoot;
use Pinoox\PinxCli\Support\ProjectScaffolder;
use Pinoox\PinxCli\Support\TemplatePath;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'new',
    description: 'Create a new single-app Pinoox project (pinoox/app template)',
)]
final class NewCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addArgument('directory', InputArgument::OPTIONAL, 'Project directory name')
            ->addOption('package', 'p', InputOption::VALUE_REQUIRED, 'App package name (com_vendor_app)')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'App display name')
            ->addOption('developer', null, InputOption::VALUE_REQUIRED, 'Developer name')
            ->addOption('no-install', null, InputOption::VALUE_NONE, 'Skip composer install');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $directory = (string) ($input->getArgument('directory') ?: '');

        if ($directory === '') {
            $directory = (string) $io->ask('Project directory', 'my-app');
        }

        $directory = ProjectRoot::normalize($directory);
        $target = str_contains($directory, '/') || preg_match('/^[A-Za-z]:/', $directory)
            ? $directory
            : ProjectRoot::normalize(getcwd() . '/' . $directory);

        $packageInput = (string) ($input->getOption('package') ?: '');
        if ($packageInput === '') {
            $default = ProjectScaffolder::normalizePackage('com_my_' . basename($target));
            $packageInput = (string) $io->ask('Package name', $default);
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

        $io->title('Creating Pinoox app project');
        $io->text([
            'Directory: <info>' . $target . '</info>',
            'Package: <info>' . $package . '</info>',
            'Template: <info>' . TemplatePath::skeletonDir() . '</info>',
        ]);

        try {
            (new ProjectScaffolder())->copySkeleton($target, $replacements);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if (!$input->getOption('no-install')) {
            $io->section('Installing dependencies');
            $code = ComposerRunner::run(['install', '--no-interaction'], $target, $output);

            if ($code !== 0) {
                $io->warning('composer install failed. Run it manually inside the project.');

                return Command::FAILURE;
            }
        }

        $io->success('Project created.');
        $io->listing([
            'cd ' . basename($target),
            'cp .env.example .env',
            'pinx setup',
            'pinx dev',
        ]);

        return Command::SUCCESS;
    }
}
