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
    name: 'db:list',
    description: 'List database connections for the current app or platform',
    aliases: ['databases'],
)]
final class DbListCommand extends Command
{
    use ForwardsPincoreCommand;

    protected function configure(): void
    {
        $this
            ->addArgument('target', InputArgument::OPTIONAL)
            ->addOption('all', null, InputOption::VALUE_NONE)
            ->addOption('test', null, InputOption::VALUE_NONE)
            ->addOption('json', null, InputOption::VALUE_NONE)
            ->setHelp('Example: pinx db:list');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->forwardPincoreCommand(
            new SymfonyStyle($input, $output),
            $input,
            $output,
            'db:list',
            ['all', 'test', 'json'],
            ['target'],
        );
    }
}
