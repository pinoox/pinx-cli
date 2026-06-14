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
    name: 'db:test',
    description: 'Test database connectivity',
    aliases: ['database:test'],
)]
final class DbTestCommand extends Command
{
    use ForwardsPincoreCommand;

    protected function configure(): void
    {
        $this
            ->addArgument('target', InputArgument::OPTIONAL)
            ->addOption('driver', null, InputOption::VALUE_REQUIRED)
            ->addOption('host', null, InputOption::VALUE_REQUIRED)
            ->addOption('database', null, InputOption::VALUE_REQUIRED)
            ->addOption('username', null, InputOption::VALUE_REQUIRED)
            ->addOption('password', null, InputOption::VALUE_REQUIRED)
            ->addOption('port', null, InputOption::VALUE_REQUIRED)
            ->setHelp('Example: pinx db:test');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->forwardPincoreCommand(
            new SymfonyStyle($input, $output),
            $input,
            $output,
            'db:test',
            ['driver', 'host', 'database', 'username', 'password', 'port'],
            ['target'],
        );
    }
}
