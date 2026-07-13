<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\AppContext;
use Pinoox\PinxCli\Support\CorePath;
use Pinoox\PinxCli\Support\DevApp;
use Pinoox\PinxCli\Support\ForwardsUserCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

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
            ->addOption('id', null, InputOption::VALUE_REQUIRED, 'Login by user id (skips password)')
            ->addOption('username', 'u', InputOption::VALUE_REQUIRED, 'Username or email')
            ->addOption('password', 'p', InputOption::VALUE_REQUIRED, 'Plain password')
            ->addOption('remember', 'r', InputOption::VALUE_NONE, 'Use remember-me lifetime')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Persist token to PINOOX_LOGIN_TOKEN')
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Clear PINOOX_LOGIN_TOKEN')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output JSON')
            ->setHelp(
                <<<'HELP'
Interactive wizard (default), or pass options:

  pinx user:login
  pinx user:login --id=1 --force
  pinx user:login --clear
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('clear')) {
            return $this->forwardUserCommand(
                $io,
                $input,
                $output,
                'user:login',
                ['clear', 'json'],
            );
        }

        if ($this->shouldRunWizard($input)) {
            if (!$this->runWizard($input, $io)) {
                return Command::FAILURE;
            }
        }

        $context = $this->requireApp($io);
        if ($context === null) {
            return Command::FAILURE;
        }

        $args = array_merge(
            ['user:login', $context->package],
            $this->forwardOptions($input, ['id', 'username', 'password', 'remember', 'force', 'json']),
            ['-n'],
        );

        return $this->runPincore($context, $args, $output);
    }

    private function shouldRunWizard(InputInterface $input): bool
    {
        if (!$input->isInteractive() || $input->getOption('json')) {
            return false;
        }

        $id = (string) ($input->getOption('id') ?? '');
        $username = (string) ($input->getOption('username') ?? '');

        return $id === '' && $username === '';
    }

    private function runWizard(InputInterface $input, SymfonyStyle $io): bool
    {
        $context = $this->requireApp($io);
        if ($context === null) {
            return false;
        }

        $io->title('User login');
        $io->text(sprintf('App: <info>%s</info>', $context->package));
        $io->newLine();

        $method = $io->choice(
            'Sign in with',
            [
                'id' => 'User id',
                'login' => 'Username or email',
                'pick' => 'Pick from user list',
            ],
            'pick',
        );

        if ($method === 'pick') {
            $users = $this->loadUsers($context, $io);
            if ($users === []) {
                $io->error('No users found for this app.');

                return false;
            }

            $labels = [];
            foreach ($users as $user) {
                $id = (string) ($user['user_id'] ?? '');
                $username = (string) ($user['username'] ?? '');
                $email = (string) ($user['email'] ?? '');
                $status = (string) ($user['status'] ?? '');
                $labels[$id] = trim(sprintf('#%s  %s  %s  [%s]', $id, $username, $email !== '' ? $email : '—', $status));
            }

            $chosenId = (string) $io->choice('Select user', $labels, array_key_first($labels));
            $input->setOption('id', $chosenId);
        } elseif ($method === 'id') {
            $id = trim((string) $io->ask('User id'));
            if ($id === '' || !ctype_digit($id)) {
                $io->error('User id must be a positive integer.');

                return false;
            }
            $input->setOption('id', $id);
        } else {
            $login = trim((string) $io->ask('Username or email'));
            if ($login === '') {
                $io->error('Username or email is required.');

                return false;
            }
            $input->setOption('username', $login);

            if ((string) ($input->getOption('password') ?? '') === '') {
                $question = new Question('Password');
                $question->setHidden(true);
                $question->setHiddenFallback(false);
                $password = (string) $io->askQuestion($question);
                if ($password === '') {
                    $io->error('Password is required.');

                    return false;
                }
                $input->setOption('password', $password);
            }
        }

        $io->section('.env auto-login');
        if ($io->confirm('Update .env with PINOOX_LOGIN_TOKEN for automatic login?', true)) {
            $input->setOption('force', true);
        }

        return true;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function loadUsers(AppContext $context, SymfonyStyle $io): array
    {
        $runner = $this->runner($context);
        $corePath = CorePath::resolve($context->root);
        $binary = $runner->binary($corePath);
        $command = ['php', $binary, 'user:list', $context->package, '--json', '-n', '--no-ansi'];
        $process = new Process(
            $command,
            $context->root,
            array_merge($_ENV, [
                'PINOOX_BASE_PATH' => $context->root,
                'PINOOX_CORE_PATH' => $corePath,
            ], DevApp::pincoreEnv($context->root)),
            null,
            60,
        );
        $process->run();

        if (!$process->isSuccessful()) {
            $io->warning('Could not load user list; choose id or username instead.');

            return [];
        }

        $stdout = trim($process->getOutput());
        $start = strpos($stdout, '[');
        if ($start === false) {
            return [];
        }

        $decoded = json_decode(substr($stdout, $start), true);
        if (!is_array($decoded)) {
            return [];
        }

        $users = [];
        foreach ($decoded as $row) {
            if (is_array($row)) {
                $users[] = $row;
            }
        }

        return $users;
    }
}
