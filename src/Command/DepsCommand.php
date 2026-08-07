<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\DepsForward;
use Pinoox\PinxCli\Support\RunsForApp;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'deps',
    description: 'Install, update, and inspect Composer and npm dependencies across the project',
    aliases: ['dep'],
)]
final class DepsCommand extends Command
{
    use DepsForward;
    use RunsForApp;

    protected function configure(): void
    {
        $this->addArgument('action', InputArgument::OPTIONAL, 'Action: status, install, update (interactive when omitted)');
        $this->configureDepsPackageArgument();
        $this->configureDepsInstallUpdateOptions();
        $this->setHelp($this->depsHelpText());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = $this->requireApp($io);

        if ($context === null) {
            return Command::FAILURE;
        }

        if (($code = $this->validateDepsOptions($input, $io)) !== null) {
            return $code;
        }

        $action = strtolower(trim((string) $input->getArgument('action')));

        if ($action === '') {
            try {
                $action = $this->resolveDepsAction($input, $output, $io);
            } catch (\Throwable $e) {
                $io->error($e->getMessage());

                return Command::FAILURE;
            }
        }

        if (!in_array($action, ['status', 'install', 'update'], true)) {
            $io->error('Unknown action "' . $action . '". Use status, install, or update.');

            return Command::INVALID;
        }

        $forwardOptions = $action === 'status'
            ? self::depsStatusOptionNames()
            : self::depsInstallUpdateOptionNames();

        $args = $this->buildDepsArgv($action, $input, $forwardOptions);

        return $this->runPincore($context, $args, $output);
    }

    private function resolveDepsAction(InputInterface $input, OutputInterface $output, SymfonyStyle $io): string
    {
        if (!$input->isInteractive()) {
            throw new \RuntimeException('Action is required in non-interactive mode. Use status, install, or update.');
        }

        $choices = [
            'status' => ['Inspect manifests and installed dependencies', 'pinx deps:status'],
            'install' => ['Install reproducibly and repair stale npm locks', 'pinx deps:install'],
            'update' => ['Update Composer and npm dependency versions', 'pinx deps:update'],
        ];

        $io->title('Pinx Dependencies');
        $io->text('Manage PHP and frontend dependencies across the project from one workflow.');
        $io->table(['Action', 'What it does', 'Command'], array_map(
            static fn (string $action, array $details): array => [$action, $details[0], $details[1]],
            array_keys($choices),
            array_values($choices),
        ));

        $question = new Question('Select action [status]: ', 'status');
        $question->setAutocompleterValues(array_keys($choices));
        $question->setValidator(static function ($answer) use ($choices): string {
            $answer = strtolower(trim((string) $answer));

            if (!array_key_exists($answer, $choices)) {
                throw new \RuntimeException('Choose status, install, or update.');
            }

            return $answer;
        });

        $helper = $this->getHelper('question');
        assert($helper instanceof QuestionHelper);

        return $helper->ask($input, $output, $question);
    }
}
