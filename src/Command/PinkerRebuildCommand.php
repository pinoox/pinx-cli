<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'pinker:rebuild', description: 'Rebuild Pinker cache for the app')]
final class PinkerRebuildCommand extends PinkerActionCommand
{
    public function __construct()
    {
        parent::__construct('pinker:rebuild', 'Rebuild Pinker cache for the app', 'rebuild', 'Example: pinx pinker:rebuild --force');
    }
}
