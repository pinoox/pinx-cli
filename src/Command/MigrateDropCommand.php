<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\AppContext;
use Pinoox\PinxCli\Support\PincoreActionCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'migrate:drop',
    description: 'Drop app tables created by migrations and clear history',
)]
final class MigrateDropCommand extends PincoreActionCommand
{
    public function __construct()
    {
        parent::__construct(
            name: 'migrate:drop',
            description: 'Drop app tables created by migrations and clear history',
            defaultArgv: [],
            forwardOptionNames: ['force'],
            help: 'Example: pinx migrate:drop --force',
        );
    }

    protected function configureOptions(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Skip confirmation prompt');
    }

    /**
     * @return list<string>
     */
    protected function pincoreArgv(AppContext $context, InputInterface $input): array
    {
        return ['migrate:drop', $context->package, '-n'];
    }
}
