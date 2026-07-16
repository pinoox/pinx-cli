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
    name: 'user:role',
    description: 'Assign roles to a user',
    aliases: ['user:role:assign'],
)]
final class UserRoleCommand extends Command
{
    use ForwardsUserCommand;

    protected function configure(): void
    {
        $this
            ->addArgument('user', InputArgument::OPTIONAL, 'User id, username, email, mobile, or personal id')
            ->addOption('role', 'r', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Role key (repeatable)')
            ->addOption('sync', null, InputOption::VALUE_NONE, 'Replace existing roles')
            ->addOption('detach', null, InputOption::VALUE_NONE, 'Remove role(s) instead of attaching')
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'List current roles and exit')
            ->setHelp(
                <<<'HELP'
Manage roles for a user. Run without a user argument to pick interactively.

Find users by id, username, email, mobile, or personal id.

Examples:
  pinx user:role
  pinx user:role admin --role=admin
  pinx user:role 09120000000 --list
  pinx user:role admin --role=editor --sync
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = $this->requireApp($io);
        if ($context === null) {
            return Command::FAILURE;
        }

        if ($input->isInteractive() && trim((string) ($input->getArgument('user') ?? '')) === '') {
            $io->title('Manage user roles');
            $io->text('Pick a user by id, username, email, mobile, or personal id.');
            $io->newLine();
        }

        $userId = $this->resolveForwardUserId($io, $input, $output, $context, 'Select user');
        if ($userId === null) {
            return Command::FAILURE;
        }

        return $this->forwardUserCommand(
            $io,
            $input,
            $output,
            'user:role',
            ['role', 'sync', 'detach', 'list'],
            $userId,
        );
    }
}
