<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\ForwardsPincoreCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'devdb:export', description: 'Export Pinoox DevDB as JSON')]
final class DevDbExportCommand extends Command
{
    use ForwardsPincoreCommand;

    protected function configure(): void
    {
        $this->addArgument('file', InputArgument::OPTIONAL);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->forwardPincoreCommand(new SymfonyStyle($input, $output), $input, $output, 'devdb:export', [], ['file'], false);
    }
}

