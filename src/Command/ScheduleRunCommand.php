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
    name: 'schedule:run',
    description: 'Run due cron tasks for the app',
)]
final class ScheduleRunCommand extends Command
{
    use RunsForApp;

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show tasks without executing')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Run even when not due');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = $this->requireApp($io);

        if ($context === null) {
            return Command::FAILURE;
        }

        $args = array_merge(
            ['schedule:run', $context->package],
            $this->forwardOptions($input, ['dry-run', 'force']),
        );

        return $this->runPincore($context, $args, $output);
    }
}
