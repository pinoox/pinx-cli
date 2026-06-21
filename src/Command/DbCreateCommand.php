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
    name: 'db:create',
    description: 'Configure platform or app database settings',
    aliases: ['database:create', 'make:db'],
)]
final class DbCreateCommand extends Command
{
    use ForwardsPincoreCommand;

    protected function configure(): void
    {
        $this
            ->addArgument('target', InputArgument::OPTIONAL)
            ->addOption('name', null, InputOption::VALUE_REQUIRED)
            ->addOption('default', null, InputOption::VALUE_NONE)
            ->addOption('driver', null, InputOption::VALUE_REQUIRED)
            ->addOption('use', 'u', InputOption::VALUE_REQUIRED)
            ->addOption('host', null, InputOption::VALUE_REQUIRED)
            ->addOption('database', null, InputOption::VALUE_REQUIRED)
            ->addOption('username', null, InputOption::VALUE_REQUIRED)
            ->addOption('password', null, InputOption::VALUE_REQUIRED)
            ->addOption('prefix', null, InputOption::VALUE_REQUIRED)
            ->addOption('port', null, InputOption::VALUE_REQUIRED)
            ->addOption('timezone', null, InputOption::VALUE_REQUIRED)
            ->addOption('set', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY)
            ->addOption('test', 't', InputOption::VALUE_NONE)
            ->setHelp('Example: pinx db:create');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->forwardPincoreCommand(
            new SymfonyStyle($input, $output),
            $input,
            $output,
            'db:create',
            ['name', 'default', 'driver', 'use', 'host', 'database', 'username', 'password', 'prefix', 'port', 'timezone', 'set', 'test'],
            ['target'],
        );
    }
}
