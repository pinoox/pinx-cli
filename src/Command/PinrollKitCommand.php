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
    name: 'kit',
    description: 'Build a PinGate zip kit to extract into public_html (no FTP)',
    aliases: ['pinroll:kit'],
)]
final class PinrollKitCommand extends Command
{
    use ForwardsPincoreCommand;
    use RequiresPinroll;

    protected function configure(): void
    {
        $this
            ->addArgument('host', InputArgument::OPTIONAL, 'Pinroll host name')
            ->addOption('site', null, InputOption::VALUE_REQUIRED, 'Site origin (https://example.com)')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Deploy folder (default public_html)')
            ->addOption('rotate', null, InputOption::VALUE_NONE, 'Mint a new gate.token')
            ->setHelp('Requires pinoox/pinroll. Example: pinx kit');
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
            'pinroll:kit',
            ['site', 'path', 'rotate'],
            ['host'],
            false,
        );
    }
}
