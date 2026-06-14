<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\ForwardsPincoreCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'file:delete',
    description: 'Delete a file record and/or storage asset',
    aliases: ['file:remove'],
)]
final class FileDeleteCommand extends Command
{
    use ForwardsPincoreCommand;

    protected function configure(): void
    {
        $this
            ->addArgument('file', InputArgument::REQUIRED)
            ->addOption('db-only', null, InputOption::VALUE_NONE)
            ->addOption('storage-only', null, InputOption::VALUE_NONE)
            ->addOption('force', null, InputOption::VALUE_NONE)
            ->setHelp('Example: pinx file:delete');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->forwardPincoreCommand(
            new SymfonyStyle($input, $output),
            $input,
            $output,
            'file:delete',
            ['db-only', 'storage-only', 'force'],
            ['file'],
        );
    }
}
