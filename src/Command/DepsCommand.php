<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\DepsForward;
use Pinoox\PinxCli\Support\RunsForApp;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
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
            ? self::depsStatusForwardOptionNames()
            : self::depsForwardOptionNames();

        $args = $this->buildDepsArgv($action, $input, $forwardOptions);

        return $this->runPincore($context, $args, $output);
    }

    private function resolveDepsAction(InputInterface $input, OutputInterface $output, SymfonyStyle $io): string
    {
        if (!$input->isInteractive()) {
            throw new \RuntimeException('Action is required in non-interactive mode. Use status, install, or update.');
        }

        $choices = [
            'status' => 'Show dependency inventory',
            'install' => 'Install Composer and npm dependencies',
            'update' => 'Update Composer and npm dependencies',
        ];

        $io->section('Dependency action');
        $io->table(['Action', 'Description'], array_map(
            static fn (string $action, string $description): array => [$action, $description],
            array_keys($choices),
            array_values($choices),
        ));

        $question = new Question('Select action [status]: ', 'status');
        $question->setAutocompleterValues(array_keys($choices));
        $question->setValidator(static function ($answer) use ($choices): string {
            $answer = strtolower(trim((string) $answer));

            if (!isset($choices[$answer])) {
                throw new \RuntimeException('Choose status, install, or update.');
            }

            return $answer;
        });

        return $this->getHelper('question')->ask($input, $output, $question);
    }
}
