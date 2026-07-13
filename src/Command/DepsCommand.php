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
use Symfony\Component\Console\Question\Question;
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
            ->addArgument('action', InputArgument::OPTIONAL, 'Action: status, install, update (interactive when omitted)')
            ->addOption('composer-only', null, InputOption::VALUE_NONE, 'Only Composer targets')
            ->addOption('npm-only', null, InputOption::VALUE_NONE, 'Only npm targets')
            ->addOption('theme', null, InputOption::VALUE_REQUIRED, 'Theme folder, context (site, panel, …), or all')
            ->addOption('all-themes', null, InputOption::VALUE_NONE, 'Every theme context or folder with package.json')
            ->addOption('production', null, InputOption::VALUE_NONE, 'Composer without dev dependencies')
            ->addOption('no-ci', null, InputOption::VALUE_NONE, 'npm install instead of ci')
            ->addOption('plain', null, InputOption::VALUE_NONE, 'Plain output for CI')
            ->addOption('continue-on-error', null, InputOption::VALUE_NONE, 'Continue when a step fails')
            ->setHelp(
                <<<'HELP'
Examples:
  pinx deps
  pinx deps status
  pinx deps install
  pinx deps update --npm-only
  pinx deps install --theme=panel
  pinx deps install --theme=all
  pinx deps install --all-themes

Dedicated commands:
  pinx deps:status
  pinx deps:install
  pinx deps:update
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = $this->requireApp($io);

        if ($context === null) {
            return Command::FAILURE;
        }

        $action = strtolower(trim((string) $input->getArgument('action')));

        if ($action === '') {
            try {
                $action = $this->resolveDepsAction($input, $output, $io);
            } catch (\Throwable $e) {
                $io->error($e->getMessage());

                return Command::FAILURE;
            }
        }

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

    private function resolveDepsAction(InputInterface $input, OutputInterface $output, SymfonyStyle $io): string
    {
        if (!$input->isInteractive()) {
            throw new \RuntimeException('Action is required in non-interactive mode. Use status, install, or update.');
        }

        $choices = [
            'status' => 'Show dependency inventory',
            'install' => 'Install Composer and npm dependencies',
            'update' => 'Update Composer and npm dependencies',
        ];

        $io->section('Dependency action');
        $io->table(['Action', 'Description'], array_map(
            static fn (string $action, string $description): array => [$action, $description],
            array_keys($choices),
            array_values($choices),
        ));

        $question = new Question('Select action [status]: ', 'status');
        $question->setAutocompleterValues(array_keys($choices));
        $question->setValidator(static function ($answer) use ($choices): string {
            $answer = strtolower(trim((string) $answer));

            if (!isset($choices[$answer])) {
                throw new \RuntimeException('Choose status, install, or update.');
            }

            return $answer;
        });

        return $this->getHelper('question')->ask($input, $output, $question);
    }
}
