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
    name: 'role:list',
    description: 'List roles for the current app',
    aliases: ['roles'],
)]
final class RoleListCommand extends Command
{
    use ForwardsPincoreCommand;

    protected function configure(): void
    {
        $this
            ->addOption('json', null, InputOption::VALUE_NONE)
            ->setHelp('Example: pinx role:list');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->forwardPincoreCommand(
            new SymfonyStyle($input, $output),
            $input,
            $output,
            'role:list',
            ['json'],
            [],
        );
    }
}
