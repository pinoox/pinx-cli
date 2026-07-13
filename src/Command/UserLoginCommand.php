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
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Write PINOOX_LOGIN_TOKEN to .env')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output JSON')
            ->setHelp(
                <<<'HELP'
Prints a token for browser apply / cookie / JWT.

With --force, writes PINOOX_LOGIN_TOKEN to .env.
Does not write PINOOX_LOGIN (optional manual .env only).

  pinx user:login
  pinx user:login --id=1
  pinx user:login --id=1 --force
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

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

        $method = $io->choice(
            'Sign in with',
            ['pick' => 'Pick from user list', 'id' => 'User id', 'login' => 'Username or email'],
            'pick',
        );

        if ($method === 'pick') {
            $users = $this->loadUsers($context, $io);
            if ($users === []) {
                $io->error('No users found.');

                return false;
            }

            $choices = [];
            foreach ($users as $user) {
                $id = (string) ($user['user_id'] ?? '');
                if ($id === '') {
                    continue;
                }
                $choices[$id] = sprintf(
                    '#%s %s (%s)',
                    $id,
                    (string) ($user['username'] ?? ''),
                    (string) ($user['status'] ?? ''),
                );
            }

            if ($choices === []) {
                $io->error('No users found.');

                return false;
            }

            $input->setOption('id', (string) $io->choice('Select user', $choices));
        } elseif ($method === 'id') {
            $id = (string) $io->ask('User id');
            if ($id === '' || !ctype_digit($id)) {
                $io->error('User id must be a positive integer.');

                return false;
            }
            $input->setOption('id', $id);
        } else {
            $login = (string) $io->ask('Username or email');
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
                'PINOOX_PACKAGE' => $context->package,
                DevApp::ENV => '1',
            ]),
        );
        $process->setTimeout(120);
        $process->run();

        if (!$process->isSuccessful()) {
            $io->warning(trim($process->getErrorOutput() ?: $process->getOutput()) ?: 'Could not list users.');

            return [];
        }

        $decoded = json_decode($process->getOutput(), true);
        if (!is_array($decoded)) {
            return [];
        }

        if (isset($decoded['users']) && is_array($decoded['users'])) {
            return array_values(array_filter($decoded['users'], 'is_array'));
        }

        return array_values(array_filter($decoded, 'is_array'));
    }
}
