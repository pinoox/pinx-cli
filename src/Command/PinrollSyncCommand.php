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
    name: 'pinroll:sync',
    description: 'Zip a local folder, upload, and extract on the host via PinGate',
    aliases: ['pinroll:push:path', 'pinroll:path:sync'],
)]
final class PinrollSyncCommand extends Command
{
    use ForwardsPincoreCommand;
    use RequiresPinroll;

    protected function configure(): void
    {
        $this
            ->addArgument('host', InputArgument::OPTIONAL, 'Pinroll host name (omit when default_host is set)')
            ->addOption('from', 'f', InputOption::VALUE_REQUIRED, 'Local directory to upload')
            ->addOption('to', 't', InputOption::VALUE_REQUIRED, 'Remote path relative to deploy root (e.g. storage/public)')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Host override')
            ->addOption('via', null, InputOption::VALUE_REQUIRED, 'Transport override: ftp, ssh, pinion')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be uploaded without sending files')
            ->setHelp(<<<'HELP'
Requires pinoox/pinroll.

Zip any local folder and sync it to the host (ftp / ssh / pinion + PinGate extract).
Remote path is relative to the deploy root — no leading slash.

Examples:
  pinx pinroll:sync --from=./storage/public --to=storage/public
  pinx pinroll:sync --from=./vendor/pinoox/pincore --to=vendor/pinoox/pincore
  pinx pinroll:sync --from=./storage/public --to=storage/public --dry-run
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

        $from = trim((string) ($input->getOption('from') ?: ''));
        $to = trim((string) ($input->getOption('to') ?: ''));

        if ($from === '' || $to === '') {
            $io->error(
                'Both --from and --to are required.' . PHP_EOL
                . 'Example: pinx pinroll:sync --from=./storage/public --to=storage/public',
            );

            return Command::FAILURE;
        }

        return $this->forwardPincoreCommand(
            $io,
            $input,
            $output,
            'pinroll:sync',
            ['from', 'to', 'via', 'dry-run', 'host'],
            ['host'],
            false,
        );
    }
}
