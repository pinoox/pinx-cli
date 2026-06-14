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
    name: 'db:prefix',
    description: 'Change the app table prefix',
    aliases: ['database:prefix'],
)]
final class DbPrefixCommand extends Command
{
    use ForwardsPincoreCommand;

    protected function configure(): void
    {
        $this
            ->addArgument('package', InputArgument::OPTIONAL)
            ->addArgument('prefix', InputArgument::OPTIONAL)
            ->addOption('use', 'u', InputOption::VALUE_REQUIRED)
            ->setHelp('Example: pinx db:prefix');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->forwardPincoreCommand(
            new SymfonyStyle($input, $output),
            $input,
            $output,
            'db:prefix',
            ['use'],
            ['package', 'prefix'],
        );
    }
}
