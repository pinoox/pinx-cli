<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'pinker:overrides', description: 'List Pinker override files for the app')]
final class PinkerOverridesCommand extends PinkerActionCommand
{
    public function __construct()
    {
        parent::__construct('pinker:overrides', 'List Pinker override files for the app', 'overrides', 'Example: pinx pinker:overrides');
    }
}
