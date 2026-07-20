<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\AppContext;
use Pinoox\PinxCli\Support\PincoreActionCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'migrate:fresh',
    description: 'Drop app tables and re-run all migrations',
)]
final class MigrateFreshCommand extends PincoreActionCommand
{
    public function __construct()
    {
        parent::__construct(
            name: 'migrate:fresh',
            description: 'Drop app tables and re-run all migrations',
            defaultArgv: [],
            forwardOptionNames: ['force'],
            help: 'Example: pinx migrate:fresh --force',
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
        return ['migrate:fresh', $context->package, '-n'];
    }
}
