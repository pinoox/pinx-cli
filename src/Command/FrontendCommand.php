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
    name: 'frontend',
    description: 'Build, run, or scaffold theme frontend assets',
    aliases: ['fe'],
)]
final class FrontendCommand extends Command
{
    use RunsForApp;

    private const ACTIONS = ['info', 'install', 'build', 'dev', 'run', 'scaffold'];

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'Action: ' . implode(', ', self::ACTIONS))
            ->addOption('theme', 't', InputOption::VALUE_REQUIRED, 'Theme folder name')
            ->addOption('script', null, InputOption::VALUE_REQUIRED, 'npm script name (run action)')
            ->addOption('stack', null, InputOption::VALUE_REQUIRED, 'Stack for scaffold: vue, react, twig')
            ->addOption('serve-host', null, InputOption::VALUE_REQUIRED, 'Dev server host')
            ->addOption('serve-port', null, InputOption::VALUE_REQUIRED, 'Dev server port')
            ->addOption('open', 'o', InputOption::VALUE_NONE, 'Open browser after start')
            ->addOption('install', null, InputOption::VALUE_NONE, 'Force npm install')
            ->setHelp('Examples: pinx frontend info | pinx fe dev | pinx fe build');
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
            ['fe', $context->package, $action],
            $this->forwardOptions($input, [
                'theme',
                'script',
                'stack',
                'serve-host',
                'serve-port',
                'open',
                'install',
            ]),
        );

        return $this->runPincore($context, $args, $output);
    }
}
