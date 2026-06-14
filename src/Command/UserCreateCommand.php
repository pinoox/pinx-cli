<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\ForwardsUserCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'user:create',
    description: 'Create a user for the current app',
    aliases: ['make:user'],
)]
final class UserCreateCommand extends Command
{
    use ForwardsUserCommand;

    protected function configure(): void
    {
        $this
            ->addOption('username', 'u', InputOption::VALUE_REQUIRED, 'Login username')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Plain password')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Email address')
            ->addOption('fname', null, InputOption::VALUE_REQUIRED, 'First name')
            ->addOption('lname', null, InputOption::VALUE_REQUIRED, 'Last name')
            ->addOption('mobile', null, InputOption::VALUE_REQUIRED, 'Mobile number')
            ->addOption('group-key', null, InputOption::VALUE_REQUIRED, 'Group key')
            ->addOption('status', 's', InputOption::VALUE_REQUIRED, 'Status: active, inactive, suspend, pending')
            ->addOption('role', 'r', InputOption::VALUE_REQUIRED, 'Attach role by role_key')
            ->setHelp('Example: pinx user:create --username=admin --password=secret --email=admin@example.com');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->forwardUserCommand(
            new SymfonyStyle($input, $output),
            $input,
            $output,
            'user:create',
            ['username', 'password', 'email', 'fname', 'lname', 'mobile', 'group-key', 'status', 'role'],
        );
    }
}
