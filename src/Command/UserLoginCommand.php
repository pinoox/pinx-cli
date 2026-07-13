<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\ForwardsUserCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'user:login',
    description: 'Authenticate a user and print a session/JWT token',
)]
final class UserLoginCommand extends Command
{
    use ForwardsUserCommand;

    protected function configure(): void
    {
        $this
            ->addOption('username', 'u', InputOption::VALUE_REQUIRED, 'Username or email')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Plain password')
            ->addOption('remember', 'r', InputOption::VALUE_NONE, 'Use remember-me lifetime')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output JSON')
            ->setHelp('Example: pinx user:login --username=admin --password=secret');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->promptMissingCredentials($input, $io);

        return $this->forwardUserCommand(
            $io,
            $input,
            $output,
            'user:login',
            ['username', 'password', 'remember', 'json'],
        );
    }

    private function promptMissingCredentials(InputInterface $input, SymfonyStyle $io): void
    {
        if (!$input->isInteractive()) {
            return;
        }

        if ((string) ($input->getOption('username') ?? '') === '') {
            $username = (string) $io->ask('Username or email');
            if ($username !== '') {
                $input->setOption('username', $username);
            }
        }

        if ((string) ($input->getOption('password') ?? '') === '') {
            $question = new Question('Password');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = (string) $io->askQuestion($question);
            if ($password !== '') {
                $input->setOption('password', $password);
            }
        }
    }
}
