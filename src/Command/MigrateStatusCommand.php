<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\AppContext;
use Pinoox\PinxCli\Support\PincoreActionCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;

#[AsCommand(
    name: 'migrate:status',
    description: 'Show migration status for the current app',
)]
final class MigrateStatusCommand extends PincoreActionCommand
{
    public function __construct()
    {
        parent::__construct(
            name: 'migrate:status',
            description: 'Show migration status for the current app',
            defaultArgv: [],
            help: 'Example: pinx migrate:status',
        );
    }

    /**
     * @return list<string>
     */
    protected function pincoreArgv(AppContext $context, InputInterface $input): array
    {
        return ['migrate:status', $context->package];
    }
}
