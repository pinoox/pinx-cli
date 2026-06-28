<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\ComposerRunner;
use Pinoox\PinxCli\Support\NewProjectWizard;
use Pinoox\PinxCli\Support\ProjectScaffolder;
use Pinoox\PinxCli\Support\WizardCancelledException;
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
            ->addOption('package', 'p', InputOption::VALUE_REQUIRED, 'App package name (e.g. com_acme_shop, ir_yekdo_app)')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'App display name')
            ->addOption('developer', null, InputOption::VALUE_REQUIRED, 'Developer name')
            ->addOption('no-install', null, InputOption::VALUE_NONE, 'Skip composer install')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation prompt')
            ->setHelp(
                <<<'HELP'
Creates a new single-app Pinoox project from the pinoox/app template.

Interactive wizard (recommended):
  pinx new

Quick create:
  pinx new my-shop --package=com_acme_shop --name="Shop App" --developer="yoosef"

Package examples:
  com_acme_shop
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $wizard = new NewProjectWizard($io);

        try {
            $info = $wizard->runForNew(
                directory: (string) ($input->getArgument('directory') ?: ''),
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

        try {
            (new ProjectScaffolder())->createProject($info['target'], $replacements, $output);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if (!$input->getOption('no-install')) {
            $io->section('Installing dependencies');
            $code = ComposerRunner::run(['install', '--no-interaction'], $info['target'], $output);

            if ($code !== 0) {
                $io->warning('composer install failed. Run it manually inside the project.');

                return Command::FAILURE;
            }
        }

        $io->success('Project created.');
        $io->listing([
            'cd ' . basename($info['target']),
            'pinx migrate',
            'pinx dev',
        ]);

        return Command::SUCCESS;
    }
}
