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
    name: 'user:password',
    description: 'Reset a user password',
    aliases: ['user:passwd'],
)]
final class UserPasswordCommand extends Command
{
    use ForwardsUserCommand;

    protected function configure(): void
    {
        $this
            ->addArgument('user', InputArgument::REQUIRED, 'User id, username, or email')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'New plain password')
            ->addOption('revoke-sessions', null, InputOption::VALUE_NONE, 'Revoke active tokens after reset')
            ->setHelp('Example: pinx user:password admin --password=secret --revoke-sessions');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->forwardUserCommand(
            new SymfonyStyle($input, $output),
            $input,
            $output,
            'user:password',
            ['password', 'revoke-sessions'],
        );
    }
}
