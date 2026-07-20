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
            ->addOption('devdb', null, InputOption::VALUE_NONE, 'Run migrations using Pinoox DevDB in local development')
            ->addOption('preview', null, InputOption::VALUE_NONE, 'Preview DevDB schema metadata without writing project DevDB files')
            ->addOption('platform', null, InputOption::VALUE_NONE, 'Also run migrate:platform before the app')
            ->addOption('reset', 'r', InputOption::VALUE_NONE, 'Rollback all app migration batches via down()')
            ->addOption('fresh', null, InputOption::VALUE_NONE, 'Drop app tables, clear history, then migrate')
            ->addOption('refresh', null, InputOption::VALUE_NONE, 'Rollback all batches via down(), then migrate again')
            ->setHelp(
                <<<'HELP'
Run pending migrations for the detected app package.

Related commands:
  pinx migrate:st      (migrate:status)
  pinx migrate:rb      (migrate:rollback)
  pinx migrate:cr <name>  (migrate:create)
  pinx migrate:pl      (migrate:platform)
  pinx migrate:reset
  pinx migrate:drop
  pinx migrate:fresh

Examples:
  pinx migrate         (migrate:run)
  pinx migrate:run
  pinx migrate --platform
  pinx migrate --fresh
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
        $useDevDb = (bool) $input->getOption('devdb');
        $preview = (bool) $input->getOption('preview');
        $reset = (bool) $input->getOption('reset');
        $fresh = (bool) $input->getOption('fresh');
        $refresh = (bool) $input->getOption('refresh');

        if ($input->getOption('platform') && !$reset && !$fresh && !$refresh) {
            $platformArgs = ['migrate', 'platform', '-n'];
            if ($useDevDb) {
                $platformArgs[] = '--devdb';
            }
            if ($preview) {
                $platformArgs[] = '--preview';
            }

            if ($runner->run($platformArgs, $output) !== 0) {
                return Command::FAILURE;
            }
        }

        $args = ['migrate', $context->package, '-n'];
        if ($useDevDb) {
            $args[] = '--devdb';
        }
        if ($preview) {
            $args[] = '--preview';
        }
        if ($fresh) {
            $args[] = '--fresh';
        } elseif ($refresh) {
            $args[] = '--refresh';
        } elseif ($reset) {
            $args[] = '--reset';
        }

        return $runner->run($args, $output) === 0
            ? Command::SUCCESS
            : Command::FAILURE;
    }
}
