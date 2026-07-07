<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\FeForwardOptions;
use Pinoox\PinxCli\Support\RunsForApp;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'frontend',
    description: 'Build, run, or scaffold theme frontend assets',
    aliases: ['fe'],
)]
final class FrontendCommand extends Command
{
    use RunsForApp;

    protected function configure(): void
    {
        $this->addArgument('action', InputArgument::REQUIRED, 'Action: ' . implode(', ', FeForwardOptions::ACTIONS));
        $this->addForwardOptionsFromFe();
        $this->setHelp(
                <<<'HELP'
Examples:
  pinx frontend info
  pinx fe dev
  pinx fe dev:apps
  pinx fe watch
  pinx fe build

Dedicated commands:
  pinx fe:info
  pinx fe:install
  pinx fe:build
  pinx fe:dev
  pinx fe:watch
  pinx fe:dev:apps
  pinx fe:scaffold

Dev opens the PHP URL (HMR via vite_tags) — not the Vite port.
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

        if (!in_array($action, FeForwardOptions::ACTIONS, true)) {
            $io->error('Unknown action "' . $action . '". Use: ' . implode(', ', FeForwardOptions::ACTIONS));

            return Command::INVALID;
        }

        $forwardNames = match ($action) {
            'dev' => FeForwardOptions::devForwardNames(),
            'dev:apps' => FeForwardOptions::devAppsForwardNames(),
            'watch' => FeForwardOptions::watchForwardNames(),
            default => FeForwardOptions::basicForwardNames(),
        };

        $args = array_merge(
            ['fe', $context->package, $action],
            $this->forwardOptions($input, $forwardNames),
        );

        $extraEnv = in_array($action, ['dev', 'dev:apps'], true) ? ['PINOOX_VITE_HMR' => '1'] : [];

        return $this->runPincore($context, $args, $output, $extraEnv);
    }

    private function addForwardOptionsFromFe(): void
    {
        foreach ([
            ...FeForwardOptions::theme(),
            ...FeForwardOptions::scaffold(),
            ...FeForwardOptions::runScript(),
            ...FeForwardOptions::install(),
            ...FeForwardOptions::dev(),
        ] as $definition) {
            $this->addOption(
                (string) $definition[0],
                $definition[1] ?? null,
                $definition[2] ?? InputOption::VALUE_NONE,
                $definition[3] ?? '',
            );
        }
    }
}
