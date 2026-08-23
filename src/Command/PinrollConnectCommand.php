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
    name: 'connect',
    description: 'Connect Pinroll to a host (PinGate via FTP, SSH, or kit)',
    aliases: ['pinroll:connect'],
)]
final class PinrollConnectCommand extends Command
{
    use ForwardsPincoreCommand;
    use RequiresPinroll;

    protected function configure(): void
    {
        $this
            ->addArgument('host', InputArgument::OPTIONAL, 'Pinroll host name')
            ->addOption('via', null, InputOption::VALUE_REQUIRED, 'Transport: ftp, ssh, pinion')
            ->addOption('bootstrap-ftp', null, InputOption::VALUE_NONE, 'Upload gate via FTP once, then set via=pinion')
            ->addOption('reset', null, InputOption::VALUE_NONE, 'Re-run deploy path, site URL, and PinGate setup')
            ->setHelp('Requires pinoox/pinroll. Example: pinx connect --via=ftp');
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
            'pinroll:connect',
            ['via', 'bootstrap-ftp', 'reset'],
            ['host'],
            false,
        );
    }
}
