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
    name: 'token:update',
    description: 'Update a token',
)]
final class TokenUpdateCommand extends Command
{
    use ForwardsPincoreCommand;

    protected function configure(): void
    {
        $this
            ->addArgument('token', InputArgument::REQUIRED)
            ->addOption('name', null, InputOption::VALUE_REQUIRED)
            ->addOption('data', null, InputOption::VALUE_REQUIRED)
            ->addOption('json', null, InputOption::VALUE_REQUIRED)
            ->addOption('extend', null, InputOption::VALUE_NONE)
            ->addOption('lifetime', 'l', InputOption::VALUE_REQUIRED)
            ->addOption('unit', null, InputOption::VALUE_REQUIRED)
            ->setHelp('Example: pinx token:update');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->forwardPincoreCommand(
            new SymfonyStyle($input, $output),
            $input,
            $output,
            'token:update',
            ['name', 'data', 'json', 'extend', 'lifetime', 'unit'],
            ['token'],
        );
    }
}
