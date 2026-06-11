<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\RunsForApp;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'migrate:create',
    description: 'Create a new database migration file for the current app',
)]
final class MigrateCreateCommand extends Command
{
    use RunsForApp;

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Migration name (e.g. create_products_table)')
            ->setHelp('Examples: pinx migrate:create create_products_table');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = $this->requireApp($io);

        if ($context === null) {
            return Command::FAILURE;
        }

        $name = trim((string) $input->getArgument('name'));

        if ($name === '') {
            $io->error('Migration name is required.');

            return Command::INVALID;
        }

        return $this->runPincore($context, ['migrate:create', $name, $context->package], $output);
    }
}
