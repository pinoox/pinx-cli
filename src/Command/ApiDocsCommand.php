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
    name: 'api:docs',
    description: 'Generate REST API documentation for the app',
)]
final class ApiDocsCommand extends Command
{
    use RunsForApp;

    protected function configure(): void
    {
        $this
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: md or html', 'md')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file or directory')
            ->addOption('openapi', null, InputOption::VALUE_NONE, 'Also export OpenAPI JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = $this->requireApp($io);

        if ($context === null) {
            return Command::FAILURE;
        }

        $args = array_merge(
            ['api:docs', $context->package],
            $this->forwardOptions($input, ['format', 'output', 'openapi']),
        );

        return $this->runPincore($context, $args, $output);
    }
}
