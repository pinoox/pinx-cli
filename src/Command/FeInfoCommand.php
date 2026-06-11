<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'fe:info', description: 'Show theme frontend stack and npm scripts')]
final class FeInfoCommand extends FeActionCommand
{
    public function __construct()
    {
        parent::__construct(
            name: 'fe:info',
            description: 'Show theme frontend stack and npm scripts',
            action: 'info',
            help: 'Example: pinx fe:info',
        );
    }
}
