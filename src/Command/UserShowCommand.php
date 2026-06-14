<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\ForwardsUserCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'user:show',
    description: 'Show a user for the current app',
)]
final class UserShowCommand extends Command
{
    use ForwardsUserCommand;

    protected function configure(): void
    {
        $this
            ->addArgument('user', InputArgument::REQUIRED, 'User id, username, or email')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output JSON')
            ->setHelp('Example: pinx user:show admin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->forwardUserCommand(
            new SymfonyStyle($input, $output),
            $input,
            $output,
            'user:show',
            ['json'],
        );
    }
}
