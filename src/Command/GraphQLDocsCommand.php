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
    name: 'graphql:docs',
    description: 'Generate GraphQL schema documentation for the app',
)]
final class GraphQLDocsCommand extends Command
{
    use RunsForApp;

    protected function configure(): void
    {
        $this
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: md or html', 'md')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file or directory');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = $this->requireApp($io);

        if ($context === null) {
            return Command::FAILURE;
        }

        $args = array_merge(
            ['graphql:docs', $context->package],
            $this->forwardOptions($input, ['format', 'output']),
        );

        return $this->runPincore($context, $args, $output);
    }
}
