<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

trait ForwardsUserCommand
{
    use RunsForApp;

    /**
     * @param list<string> $optionNames
     */
    protected function forwardUserCommand(
        SymfonyStyle $io,
        InputInterface $input,
        OutputInterface $output,
        string $pincoreCommand,
        array $optionNames = [],
        ?string $user = null,
        bool $nonInteractive = true,
    ): int {
        $context = $this->requireApp($io);

        if ($context === null) {
            return Command::FAILURE;
        }

        $args = [$pincoreCommand];

        if ($user !== null && $user !== '') {
            $args[] = $user;
        } elseif ($input->hasArgument('user')) {
            $userArg = $input->getArgument('user');
            if (is_string($userArg) && $userArg !== '') {
                $args[] = $userArg;
            }
        }

        $args[] = $context->package;

        $args = array_merge($args, $this->forwardOptions($input, $optionNames));

        if ($nonInteractive) {
            $args[] = '-n';
        }

        return $this->runPincore($context, $args, $output);
    }

    /**
     * Resolve a user id from CLI arg or interactive wizard (id / username / email / mobile / personal_id).
     * Does not dump the full user list — only shows matches when the identifier is ambiguous.
     */
    protected function resolveForwardUserId(
        SymfonyStyle $io,
        InputInterface $input,
        OutputInterface $output,
        AppContext $context,
        string $sectionTitle = 'Select user',
    ): ?string {
        $identifier = '';
        if ($input->hasArgument('user')) {
            $identifier = trim((string) ($input->getArgument('user') ?? ''));
        }

        if ($identifier === '') {
            if (!$input->isInteractive()) {
                $io->error('User identifier is required.');

                return null;
            }

            $identifier = trim((string) $io->ask('User id, username, email, mobile, or personal id'));
        }

        if ($identifier === '') {
            $io->error('User identifier is required.');

            return null;
        }

        $users = $this->loadAppUsers($context, $io);
        $matches = $this->matchUsersByIdentifier($users, $identifier);

        if ($matches === []) {
            $io->error('User not found: ' . $identifier);

            return null;
        }

        if (count($matches) === 1) {
            return (string) $matches[0]['user_id'];
        }

        if (!$input->isInteractive()) {
            $io->error(sprintf('Multiple users match "%s". Pass a unique user id.', $identifier));

            return null;
        }

        return $this->promptAmbiguousUserId($io, $input, $output, $matches);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function loadAppUsers(AppContext $context, SymfonyStyle $io): array
    {
        $runner = $this->runner($context);
        $corePath = CorePath::resolve($context->root);
        $binary = $runner->binary($corePath);
        $command = array_merge(
            ['php'],
            CliErrorReporting::phpIniArgs($context->root),
            [$binary, 'user:list', $context->package, '--json', '-n', '--no-ansi'],
        );
        $process = new Process(
            $command,
            $context->root,
            array_merge($_ENV, [
                'PINOOX_BASE_PATH' => $context->root,
                'PINOOX_CORE_PATH' => $corePath,
                'PINOOX_PACKAGE' => $context->package,
            ], DevApp::pincoreEnv($context->root)),
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

    /**
     * @param list<array<string, mixed>> $users
     * @return list<array<string, mixed>>
     */
    protected function matchUsersByIdentifier(array $users, string $identifier): array
    {
        $needle = mb_strtolower(trim($identifier));
        if ($needle === '') {
            return [];
        }

        $matches = [];
        foreach ($users as $user) {
            $candidates = [
                (string) ($user['user_id'] ?? ''),
                (string) ($user['username'] ?? ''),
                (string) ($user['email'] ?? ''),
                (string) ($user['mobile'] ?? ''),
                (string) ($user['personal_id'] ?? ''),
            ];

            foreach ($candidates as $candidate) {
                if ($candidate !== '' && mb_strtolower($candidate) === $needle) {
                    $matches[] = $user;
                    break;
                }
            }
        }

        return $matches;
    }

    /**
     * @param list<array<string, mixed>> $users
     */
    protected function renderUserTable(
        OutputInterface $output,
        SymfonyStyle $io,
        array $users,
        string $sectionTitle,
    ): void {
        $io->section($sectionTitle);

        $table = new Table($output);
        $table->setHeaders(['ID', 'Username', 'Email', 'Mobile', 'Personal ID', 'Name', 'Status']);
        foreach ($users as $user) {
            $table->addRow([
                (string) ($user['user_id'] ?? ''),
                (string) ($user['username'] ?? ''),
                (string) (($user['email'] ?? '') !== '' ? $user['email'] : '—'),
                (string) (($user['mobile'] ?? '') !== '' ? $user['mobile'] : '—'),
                (string) (($user['personal_id'] ?? '') !== '' ? $user['personal_id'] : '—'),
                trim((string) ($user['full_name'] ?? '')) !== '' ? trim((string) $user['full_name']) : '—',
                (string) ($user['status'] ?? ''),
            ]);
        }
        $table->render();
    }

    /**
     * @param list<array<string, mixed>> $users
     */
    protected function promptAmbiguousUserId(
        SymfonyStyle $io,
        InputInterface $input,
        OutputInterface $output,
        array $users,
    ): ?string {
        $io->warning(sprintf('%d users match your input. Enter the user id from the list below.', count($users)));
        $this->renderUserTable($output, $io, $users, 'Multiple users found — which one?');

        $validIds = [];
        foreach ($users as $user) {
            $id = (string) ($user['user_id'] ?? '');
            if ($id !== '') {
                $validIds[] = $id;
            }
        }

        $question = new Question('User id: ');
        $question->setAutocompleterValues($validIds);
        $answer = trim((string) $io->askQuestion($question));

        if ($answer === '' || !in_array($answer, $validIds, true)) {
            $io->error('Enter a valid user id from the list above.');

            return null;
        }

        return $answer;
    }

    protected function askHiddenPassword(SymfonyStyle $io, string $label = 'New password'): string
    {
        $question = new Question($label);
        $question->setHidden(true);
        $question->setHiddenFallback(false);

        return (string) $io->askQuestion($question);
    }
}
