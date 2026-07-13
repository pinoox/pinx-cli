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
    name: 'user:logout',
    description: 'End the current auth session (token/cookie)',
)]
final class UserLogoutCommand extends Command
{
    use ForwardsUserCommand;

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Remove PINOOX_LOGIN_TOKEN from .env')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output JSON')
            ->setHelp(
                <<<'HELP'
Log out the current auth session.

With --force, removes PINOOX_LOGIN_TOKEN from .env.
Does not change PINOOX_LOGIN.

  pinx user:logout
  pinx user:logout --force
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->forwardUserCommand(
            new SymfonyStyle($input, $output),
            $input,
            $output,
            'user:logout',
            ['force', 'json'],
        );
    }
}
