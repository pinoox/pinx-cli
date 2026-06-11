<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'pinker:status', description: 'Compare Pinker cache against project source')]
final class PinkerStatusCommand extends PinkerActionCommand
{
    public function __construct()
    {
        parent::__construct('pinker:status', 'Compare Pinker cache against project source', 'status', 'Example: pinx pinker:status');
    }
}
