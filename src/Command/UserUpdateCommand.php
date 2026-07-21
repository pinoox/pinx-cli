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
    name: 'user:update',
    description: 'Update profile fields for a user',
)]
final class UserUpdateCommand extends Command
{
    use ForwardsUserCommand;

    protected function configure(): void
    {
        $this
            ->addArgument('user', InputArgument::OPTIONAL, 'User id, username, email, mobile, or personal id')
            ->addOption('set', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Set field=value (repeatable)')
            ->addOption('meta', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Set metadata key=value (repeatable)')
            ->addOption('metadata', 'm', InputOption::VALUE_REQUIRED, 'Metadata as JSON object')
            ->addOption('username', 'u', InputOption::VALUE_REQUIRED, 'New username')
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'New email')
            ->addOption('fname', null, InputOption::VALUE_REQUIRED, 'First name')
            ->addOption('lname', null, InputOption::VALUE_REQUIRED, 'Last name')
            ->addOption('mobile', null, InputOption::VALUE_REQUIRED, 'Mobile number')
            ->addOption('group-key', null, InputOption::VALUE_REQUIRED, 'Group key')
            ->addOption('status', 's', InputOption::VALUE_REQUIRED, 'Status: active, inactive, suspend, pending')
            ->addOption('personal-id', null, InputOption::VALUE_REQUIRED, 'Personal ID')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output JSON')
            ->setHelp(
                <<<'HELP'
Update user profile fields. Run without a user argument to pick interactively.

Find users by id, username, email, mobile, or personal id.

Examples:
  pinx user:update
  pinx user:update admin --email=new@example.com
  pinx user:update 09120000000 --fname=Ali
  pinx user:update admin --meta theme=dark
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
            $io->title('Update user');
            $io->text('Pick a user by id, username, email, mobile, or personal id.');
            $io->newLine();
        }

        $userId = $this->resolveForwardUserId($io, $input, $output, $context, 'Select user to update');
        if ($userId === null) {
            return Command::FAILURE;
        }

        $hasFieldOptions = $this->hasUserUpdateOptions($input);

        return $this->forwardUserCommand(
            $io,
            $input,
            $output,
            'user:update',
            ['set', 'meta', 'metadata', 'username', 'email', 'fname', 'lname', 'mobile', 'group-key', 'status', 'personal-id', 'json'],
            $userId,
            nonInteractive: $hasFieldOptions || !$input->isInteractive(),
        );
    }

    private function hasUserUpdateOptions(InputInterface $input): bool
    {
        foreach (['username', 'email', 'fname', 'lname', 'mobile', 'group-key', 'status', 'personal-id', 'metadata'] as $option) {
            $value = $input->getOption($option);
            if (is_string($value) && $value !== '') {
                return true;
            }
        }

        foreach (['set', 'meta'] as $option) {
            $value = $input->getOption($option);
            if (is_array($value) && $value !== []) {
                return true;
            }
        }

        return false;
    }
}
