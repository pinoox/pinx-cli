<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\PincoreActionCommand;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'migrate:platform',
    description: 'Run pending platform (pincore) database migrations',
)]
final class MigratePlatformCommand extends PincoreActionCommand
{
    public function __construct()
    {
        parent::__construct(
            name: 'migrate:platform',
            description: 'Run pending platform (pincore) database migrations',
            defaultArgv: ['migrate', 'platform', '-n'],
            help: 'Example: pinx migrate:platform',
        );
    }
}
