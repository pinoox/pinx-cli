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
    description: 'Rollback the last batch of app migrations',
)]
final class MigrateRollbackCommand extends PincoreActionCommand
{
    public function __construct()
    {
        parent::__construct(
            name: 'migrate:rollback',
            description: 'Rollback the last batch of app migrations',
            defaultArgv: [],
            forwardOptionNames: ['ignore-fk'],
            help: 'Examples: pinx migrate:rollback | pinx migrate:rollback --ignore-fk',
        );
    }

    protected function configureOptions(): void
    {
        $this->addOption('ignore-fk', 'f', InputOption::VALUE_NONE, 'Disable foreign key checks during rollback');
    }

    /**
     * @return list<string>
     */
    protected function pincoreArgv(AppContext $context, InputInterface $input): array
    {
        return ['migrate:rollback', $context->package, '-n'];
    }
}
