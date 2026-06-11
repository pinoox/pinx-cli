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
    name: 'patch:run',
    description: 'Run pending data patches for the app',
    aliases: ['patch'],
)]
final class PatchRunCommand extends Command
{
    use RunsForApp;

    protected function configure(): void
    {
        $this
            ->addOption('class', 'c', InputOption::VALUE_REQUIRED, 'Run a specific patch class')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Continue when a patch fails');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = $this->requireApp($io);

        if ($context === null) {
            return Command::FAILURE;
        }

        $args = array_merge(
            ['patch:run', $context->package],
            $this->forwardOptions($input, ['class', 'force']),
        );

        return $this->runPincore($context, $args, $output);
    }
}
