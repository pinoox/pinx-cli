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
    name: 'user:delete',
    description: 'Delete a user',
)]
final class UserDeleteCommand extends Command
{
    use ForwardsUserCommand;

    protected function configure(): void
    {
        $this
            ->addArgument('user', InputArgument::REQUIRED, 'User id, username, or email')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation')
            ->addOption('revoke-sessions', null, InputOption::VALUE_NONE, 'Revoke tokens before delete')
            ->setHelp('Example: pinx user:delete demo --force');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->forwardUserCommand(
            new SymfonyStyle($input, $output),
            $input,
            $output,
            'user:delete',
            ['force', 'revoke-sessions'],
        );
    }
}
