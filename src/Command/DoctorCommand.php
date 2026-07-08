<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\RunsForApp;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'doctor',
    description: 'Deep health check: PHP, app layout, env, database, frontend, and build readiness',
)]
final class DoctorCommand extends Command
{
    use RunsForApp;

    protected function configure(): void
    {
        $this
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output machine-readable JSON report')
            ->addOption('skip-db', null, InputOption::VALUE_NONE, 'Skip database connectivity checks')
            ->addOption('skip-frontend', null, InputOption::VALUE_NONE, 'Skip Node/npm and frontend checks')
            ->addOption('no-fixes', null, InputOption::VALUE_NONE, 'Hide suggested fix commands')
            ->setHelp(
                <<<'HELP'
Runs a structured diagnostic on the current single-app Pinoox project via pincore.

Examples:
  pinx doctor
  pinx doctor --skip-db
  pinx doctor --json
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

        $args = ['doctor', $context->package, '--no-ansi'];

        foreach (['json', 'skip-db', 'skip-frontend', 'no-fixes'] as $option) {
            if ((bool) $input->getOption($option)) {
                $args[] = '--' . $option;
            }
        }

        return $this->runPincore($context, $args, $output);
    }
}
