<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\ComposerRunner;
use Pinoox\PinxCli\Support\NewProjectWizard;
use Pinoox\PinxCli\Support\ProjectRoot;
use Pinoox\PinxCli\Support\ProjectScaffolder;
use Pinoox\PinxCli\Support\WizardCancelledException;
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
            ->addOption('package', 'p', InputOption::VALUE_REQUIRED, 'App package name (e.g. com_acme_shop, ir_yekdo_app)')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'App display name')
            ->addOption('developer', null, InputOption::VALUE_REQUIRED, 'Developer name')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Continue even when app.php already exists')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation prompt');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $root = ProjectRoot::normalize(getcwd() ?: '.');

        if (is_file($root . '/app.php') && !$input->getOption('force')) {
            $io->error('app.php already exists. Use --force to scaffold missing files anyway.');

            return Command::FAILURE;
        }

        $wizard = new NewProjectWizard($io);

        try {
            $info = $wizard->runForInit(
                package: (string) ($input->getOption('package') ?: ''),
                displayName: (string) ($input->getOption('name') ?: ''),
                developer: (string) ($input->getOption('developer') ?: ''),
                skipConfirm: (bool) $input->getOption('yes'),
            );
        } catch (WizardCancelledException) {
            $io->warning('Cancelled.');

            return Command::SUCCESS;
        } catch (\InvalidArgumentException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $replacements = ProjectScaffolder::defaultReplacements(
            $info['package'],
            $info['displayName'],
            $info['developer'],
        );

        $scaffolder = new ProjectScaffolder();

        if (!is_file($root . '/composer.json')) {
            $scaffolder->copyFileFromSkeleton('composer.json', $root . '/composer.json', $replacements, $output);
        }

        $scaffolder->initInPlace($root, $replacements, $output);

        if (!is_dir($root . '/pincore') && !is_dir($root . '/vendor/pinoox/pincore')) {
            $io->section('Installing dependencies');
            $code = ComposerRunner::run(['install', '--no-interaction'], $root, $output);

            if ($code !== 0) {
                return Command::FAILURE;
            }
        }

        $io->success('Project initialized.');
        $io->text('Next: pinx migrate && pinx dev');

        return Command::SUCCESS;
    }
}
