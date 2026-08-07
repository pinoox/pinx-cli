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
    description: 'Prepare an existing app for safe single-app development',
)]
final class SyncCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('package', 'p', InputOption::VALUE_REQUIRED, 'App package name when it cannot be detected')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'App display name for generated files')
            ->addOption('developer', null, InputOption::VALUE_REQUIRED, 'Developer name for generated files')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing Pinx support files')
            ->setHelp(
                <<<'HELP'
Adds missing Pinx infrastructure and Composer dependencies for single-app development.

Creates composer.json when missing, using app.php for its name, version, and description.
Adds missing single-app dependencies from pinoox/app.
Existing dependency constraints and custom Composer settings are preserved.
It never changes app.php, routes, or other app-specific files.
Use --force only to overwrite the Pinx-managed support file list.

Examples:
  pinx sync
  pinx sync --force
HELP
            );
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
            $io->success('Pinx support files are already in sync.');

            if (!is_file($root . '/vendor/autoload.php')) {
                $io->text('Next: composer install');
            }

            return Command::SUCCESS;
        }

        $io->success('Pinx support files synced.');
        $io->listing($changed);

        if (in_array('composer.json', $changed, true) || !is_file($root . '/vendor/autoload.php')) {
            $io->text('Next: composer install');
        }

        return Command::SUCCESS;
    }
}
