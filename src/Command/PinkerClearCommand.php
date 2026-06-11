<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'pinker:clear', description: 'Clear Pinker cache for the app')]
final class PinkerClearCommand extends PinkerActionCommand
{
    public function __construct()
    {
        parent::__construct('pinker:clear', 'Clear Pinker cache for the app', 'clear', 'Example: pinx pinker:clear --force');
    }
}
