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
            ->addArgument('user', InputArgument::OPTIONAL, 'User id, username, email, mobile, or personal id')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output JSON')
            ->setHelp(
                <<<'HELP'
Show a user by id, username, email, mobile, or personal id.

Run without arguments for an interactive wizard. If several users match,
you will be asked to pick the user id.

Examples:
  pinx user:show
  pinx user:show admin
  pinx user:show 09120000000 --json
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
            $io->title('Show user');
            $io->text('Find a user by id, username, email, mobile, or personal id.');
            $io->newLine();
        }

        $userId = $this->resolveForwardUserId($io, $input, $output, $context, 'Show user');
        if ($userId === null) {
            return Command::FAILURE;
        }

        return $this->forwardUserCommand(
            $io,
            $input,
            $output,
            'user:show',
            ['json'],
            $userId,
        );
    }
}
