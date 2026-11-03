<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\ForwardsPincoreCommand;
use Pinoox\PinxCli\Support\RequiresPinroll;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'pinroll:logs',
    description: 'View or list host or server logs for Agent inspection and diagnosis',
    aliases: ['logs', 'pinroll:log', 'log'],
)]
final class PinrollLogsCommand extends Command
{
    use ForwardsPincoreCommand;
    use RequiresPinroll;

    protected function configure(): void
    {
        $this
            ->addArgument('host', InputArgument::OPTIONAL, 'Pinroll host name (omit with --local or default_host)')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Host override')
            ->addOption('file', 'f', InputOption::VALUE_REQUIRED, 'Specific log file to read (name or relative path)')
            ->addOption('latest', null, InputOption::VALUE_NONE, 'View the newest / most recent log file')
            ->addOption('lines', 'l', InputOption::VALUE_REQUIRED, 'Number of lines to tail from log', 100)
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, 'Filter by log type (app, server, pinroll, all)', 'all')
            ->addOption('search', 's', InputOption::VALUE_REQUIRED, 'Search query for log content or file names')
            ->addOption('level', null, InputOption::VALUE_REQUIRED, 'Filter lines by log level (error, warning, info, debug)')
            ->addOption('local', null, InputOption::VALUE_NONE, 'Read logs from local machine directly')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output result as machine-readable JSON (ideal for AI Agents)')
            ->addOption('raw', null, InputOption::VALUE_NONE, 'Output raw log content without formatting')
            ->setHelp(<<<'HELP'
Requires pinoox/pinroll.

View or list host or server logs, structured for AI Agents or human inspection.

Examples:
  pinx logs --json
  pinx logs --latest --lines=50
  pinx logs production --file=pinoox-2026-10-28.log --json
  pinx pinroll:logs --local
HELP);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = $this->requireApp($io);
        if ($context === null) {
            return Command::FAILURE;
        }

        if (!$this->requirePinroll($io, $context)) {
            return $this->pinrollMissing();
        }

        return $this->forwardPincoreCommand(
            $io,
            $input,
            $output,
            'pinroll:logs',
            ['host', 'file', 'latest', 'lines', 'type', 'search', 'level', 'local', 'json', 'raw'],
            ['host'],
            false,
        );
    }
}
