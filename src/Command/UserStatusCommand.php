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
    name: 'user:status',
    description: 'Change a user status',
)]
final class UserStatusCommand extends Command
{
    use ForwardsUserCommand;

    protected function configure(): void
    {
        $this
            ->addArgument('user', InputArgument::OPTIONAL, 'User id, username, email, mobile, or personal id')
            ->addOption('status', 's', InputOption::VALUE_REQUIRED, 'New status: active, inactive, suspend, pending')
            ->setHelp(
                <<<'HELP'
Set user status. Run without arguments for an interactive wizard.

Find users by id, username, email, mobile, or personal id.

Examples:
  pinx user:status
  pinx user:status admin --status=inactive
  pinx user:status 09120000000 --status=active
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

        $useWizard = $input->isInteractive()
            && trim((string) ($input->getArgument('user') ?? '')) === ''
            && !(string) ($input->getOption('status') ?? '');

        if ($useWizard) {
            $io->title('Change user status');
            $io->text('Find a user by id, username, email, mobile, or personal id, then set a new status.');
            $io->newLine();
        }

        $userId = $this->resolveForwardUserId($io, $input, $output, $context, 'Change status for');
        if ($userId === null) {
            return Command::FAILURE;
        }

        $status = (string) ($input->getOption('status') ?? '');
        if ($status === '') {
            if (!$input->isInteractive()) {
                $io->error('Status is required (pass --status=).');

                return Command::FAILURE;
            }
            $statuses = ['active', 'inactive', 'suspend', 'pending'];
            $input->setOption('status', (string) $io->choice('Status', $statuses, 'active'));
        }

        return $this->forwardUserCommand(
            $io,
            $input,
            $output,
            'user:status',
            ['status'],
            $userId,
        );
    }
}
