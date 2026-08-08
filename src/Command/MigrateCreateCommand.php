<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\RunsForApp;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
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
            ->addArgument('name', InputArgument::REQUIRED, 'Migration name (e.g. create_products_table, add_email_to_users)')
            ->addOption('create', null, InputOption::VALUE_REQUIRED, 'The table to be created')
            ->addOption('table', null, InputOption::VALUE_REQUIRED, 'The table to migrate (update stub)')
            ->setHelp(
                <<<'HELP'
Examples:
  pinx migrate:create create_products_table
  pinx migrate:create add_email_to_users
  pinx migrate:create drop_posts_table
  pinx migrate:create sync_legacy_flags --table=users
  pinx migrate:create add_status --create=orders
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

        $name = trim((string) $input->getArgument('name'));

        if ($name === '') {
            $io->error('Migration name is required.');

            return Command::INVALID;
        }

        $args = ['migrate:create', $name, $context->package];
        $args = array_merge($args, $this->forwardOptions($input, ['create', 'table']));

        return $this->runPincore($context, $args, $output);
    }
}
