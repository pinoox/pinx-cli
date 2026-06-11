<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\RunsForApp;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'deps',
    description: 'Install, update, and inspect Composer and npm dependencies for the app',
    aliases: ['dep'],
)]
final class DepsCommand extends Command
{
    use RunsForApp;

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action: status, install, update')
            ->addOption('composer-only', null, InputOption::VALUE_NONE, 'Only Composer targets')
            ->addOption('npm-only', null, InputOption::VALUE_NONE, 'Only npm targets')
            ->addOption('theme', null, InputOption::VALUE_REQUIRED, 'Theme folder name')
            ->addOption('all-themes', null, InputOption::VALUE_NONE, 'Include every theme with package.json')
            ->addOption('production', null, InputOption::VALUE_NONE, 'Composer without dev dependencies')
            ->addOption('no-ci', null, InputOption::VALUE_NONE, 'npm install instead of ci')
            ->addOption('plain', null, InputOption::VALUE_NONE, 'Plain output for CI')
            ->addOption('continue-on-error', null, InputOption::VALUE_NONE, 'Continue when a step fails')
            ->setHelp('Examples: pinx deps status | pinx deps install | pinx deps update --npm-only');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = $this->requireApp($io);

        if ($context === null) {
            return Command::FAILURE;
        }

        $action = strtolower(trim((string) $input->getArgument('action')));

        if (!in_array($action, ['status', 'install', 'update'], true)) {
            $io->error('Unknown action "' . $action . '". Use status, install, or update.');

            return Command::INVALID;
        }

        $args = array_merge(
            ['deps', $action, $context->package],
            $this->forwardOptions($input, [
                'composer-only',
                'npm-only',
                'theme',
                'all-themes',
                'production',
                'no-ci',
                'plain',
                'continue-on-error',
            ]),
        );

        return $this->runPincore($context, $args, $output);
    }
}
