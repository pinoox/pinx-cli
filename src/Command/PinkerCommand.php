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
    name: 'pinker',
    description: 'Manage Pinker cache, overrides, and rebuilds for the app',
)]
final class PinkerCommand extends Command
{
    use RunsForApp;

    private const ACTIONS = ['status', 'rebuild', 'diff', 'clear', 'overrides'];

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action: ' . implode(', ', self::ACTIONS))
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force rebuild or clear')
            ->addOption('plain', null, InputOption::VALUE_NONE, 'Plain output')
            ->setHelp('Examples: pinx pinker status | pinx pinker rebuild --force');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = $this->requireApp($io);

        if ($context === null) {
            return Command::FAILURE;
        }

        $action = strtolower(trim((string) $input->getArgument('action')));

        if (!in_array($action, self::ACTIONS, true)) {
            $io->error('Unknown action "' . $action . '". Use: ' . implode(', ', self::ACTIONS));

            return Command::INVALID;
        }

        $args = array_merge(
            ['pinker:' . $action, $context->package],
            $this->forwardOptions($input, ['force', 'plain']),
        );

        return $this->runPincore($context, $args, $output);
    }
}
