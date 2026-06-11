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
    name: 'migrate:run',
    description: 'Run pending database migrations for the current app',
)]
final class MigrateCommand extends Command
{
    use RunsForApp;

    protected function configure(): void
    {
        $this
            ->addOption('platform', null, InputOption::VALUE_NONE, 'Also run migrate:platform before the app')
            ->setHelp(
                <<<'HELP'
Run pending migrations for the detected app package.

Related commands:
  pinx migrate:st      (migrate:status)
  pinx migrate:rb      (migrate:rollback)
  pinx migrate:cr <name>  (migrate:create)
  pinx migrate:pl      (migrate:platform)

Examples:
  pinx migrate         (migrate:run)
  pinx migrate:run
  pinx migrate --platform
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

        $runner = $this->runner($context);

        if ($input->getOption('platform')) {
            if ($runner->run(['migrate', 'platform', '-n'], $output) !== 0) {
                return Command::FAILURE;
            }
        }

        return $runner->run(['migrate', $context->package, '-n'], $output) === 0
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}
