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
    name: 'route:actions',
    description: 'List, validate, and inspect named router actions',
    aliases: ['routes'],
)]
final class RouteActionsCommand extends Command
{
    use RunsForApp;

    protected function configure(): void
    {
        $this
            ->addOption('validate', null, InputOption::VALUE_NONE, 'Validate action references')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Treat unused actions as errors')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output JSON')
            ->addOption('cache', null, InputOption::VALUE_NONE, 'Show action cache paths')
            ->setHelp('Examples: pinx route:actions | pinx routes --validate');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = $this->requireApp($io);

        if ($context === null) {
            return Command::FAILURE;
        }

        $args = array_merge(
            ['route:actions', $context->package],
            $this->forwardOptions($input, ['validate', 'strict', 'json', 'cache']),
        );

        return $this->runPincore($context, $args, $output);
    }
}
