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
            ->addArgument('user', InputArgument::OPTIONAL, 'User id, username, email, mobile, or personal id')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation')
            ->addOption('revoke-sessions', null, InputOption::VALUE_NONE, 'Revoke tokens before delete')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output JSON')
            ->setHelp(
                <<<'HELP'
Delete a user. Run without arguments for an interactive wizard.

Find users by id, username, email, mobile, or personal id.

Examples:
  pinx user:delete
  pinx user:delete demo --force
  pinx user:delete 09120000000 --revoke-sessions
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
            $io->title('Delete user');
            $io->text('Find a user by id, username, email, mobile, or personal id.');
            $io->newLine();
        }

        $userId = $this->resolveForwardUserId($io, $input, $output, $context, 'Delete user');
        if ($userId === null) {
            return Command::FAILURE;
        }

        if (!$input->getOption('force')) {
            if (!$input->isInteractive()) {
                $io->error('Pass --force to delete in non-interactive mode.');

                return Command::FAILURE;
            }
            if (!$io->confirm(sprintf('Delete user #%s?', $userId), false)) {
                $io->warning('Delete canceled.');

                return Command::SUCCESS;
            }
            $input->setOption('force', true);
        }

        return $this->forwardUserCommand(
            $io,
            $input,
            $output,
            'user:delete',
            ['force', 'revoke-sessions', 'json'],
            $userId,
        );
    }
}
