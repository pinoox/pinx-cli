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
            ->addArgument('user', InputArgument::OPTIONAL, 'User id, username, email, mobile, or personal id')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'New plain password')
            ->addOption('revoke-sessions', null, InputOption::VALUE_NONE, 'Revoke active tokens after reset')
            ->setHelp(
                <<<'HELP'
Reset a user password. Run without arguments for an interactive wizard.

Find users by id, username, email, mobile, or personal id. If several match,
you will be asked to pick the user id.

Examples:
  pinx user:password
  pinx user:password admin --password=secret --revoke-sessions
  pinx user:password 09120000000
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
            && !(string) ($input->getOption('password') ?? '');

        if ($useWizard) {
            $io->title('Reset user password');
            $io->text('Find a user by id, username, email, mobile, or personal id, then set a new password.');
            $io->newLine();
        }

        $userId = $this->resolveForwardUserId($io, $input, $output, $context, 'Reset password for');
        if ($userId === null) {
            return Command::FAILURE;
        }

        $password = (string) ($input->getOption('password') ?? '');
        if ($password === '') {
            if (!$input->isInteractive()) {
                $io->error('Password is required (pass --password=).');

                return Command::FAILURE;
            }
            $password = $this->askHiddenPassword($io, 'New password');
            $input->setOption('password', $password);
        }

        if (strlen($password) < 5) {
            $io->error('Password must be at least 5 characters.');

            return Command::FAILURE;
        }

        return $this->forwardUserCommand(
            $io,
            $input,
            $output,
            'user:password',
            ['password', 'revoke-sessions'],
            $userId,
        );
    }
}
