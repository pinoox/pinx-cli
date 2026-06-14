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
    name: 'db:show',
    description: 'Show database connection details',
    aliases: ['database:show'],
)]
final class DbShowCommand extends Command
{
    use ForwardsPincoreCommand;

    protected function configure(): void
    {
        $this
            ->addArgument('target', InputArgument::OPTIONAL)
            ->addOption('json', null, InputOption::VALUE_NONE)
            ->setHelp('Example: pinx db:show');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->forwardPincoreCommand(
            new SymfonyStyle($input, $output),
            $input,
            $output,
            'db:show',
            ['json'],
            ['target'],
        );
    }
}
