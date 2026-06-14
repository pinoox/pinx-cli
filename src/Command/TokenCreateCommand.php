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
    name: 'token:create',
    description: 'Create a session token',
    aliases: ['make:token'],
)]
final class TokenCreateCommand extends Command
{
    use ForwardsPincoreCommand;

    protected function configure(): void
    {
        $this
            ->addOption('user', 'u', InputOption::VALUE_REQUIRED)
            ->addOption('name', null, InputOption::VALUE_REQUIRED)
            ->addOption('data', null, InputOption::VALUE_REQUIRED)
            ->addOption('json', null, InputOption::VALUE_REQUIRED)
            ->addOption('lifetime', 'l', InputOption::VALUE_REQUIRED)
            ->addOption('unit', null, InputOption::VALUE_REQUIRED)
            ->addOption('key', 'k', InputOption::VALUE_REQUIRED)
            ->setHelp('Example: pinx token:create');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->forwardPincoreCommand(
            new SymfonyStyle($input, $output),
            $input,
            $output,
            'token:create',
            ['user', 'name', 'data', 'json', 'lifetime', 'unit', 'key'],
            [],
        );
    }
}
