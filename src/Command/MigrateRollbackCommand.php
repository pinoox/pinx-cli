<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\AppContext;
use Pinoox\PinxCli\Support\PincoreActionCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'migrate:rollback',
    description: 'Rollback app migration batches',
)]
final class MigrateRollbackCommand extends PincoreActionCommand
{
    public function __construct()
    {
        parent::__construct(
            name: 'migrate:rollback',
            description: 'Rollback app migration batches',
            defaultArgv: [],
            forwardOptionNames: ['ignore-fk', 'step', 'all'],
            help: <<<'HELP'
Examples:
  pinx migrate:rollback
  pinx migrate:rollback --step=2
  pinx migrate:rollback --all
  pinx migrate:rollback --ignore-fk
HELP,
        );
    }

    protected function configureOptions(): void
    {
        $this
            ->addOption('step', null, InputOption::VALUE_REQUIRED, 'Number of batches to rollback', '1')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Rollback every executed batch')
            ->addOption('ignore-fk', 'f', InputOption::VALUE_NONE, 'Disable foreign key checks during rollback');
    }

    /**
     * @return list<string>
     */
    protected function pincoreArgv(AppContext $context, InputInterface $input): array
    {
        return ['migrate:rollback', $context->package, '-n'];
    }
}
