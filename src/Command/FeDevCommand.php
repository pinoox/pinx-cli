<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'fe:dev', description: 'Start Vite dev server for the theme')]
final class FeDevCommand extends FeActionCommand
{
    public function __construct()
    {
        parent::__construct(
            name: 'fe:dev',
            description: 'Start Vite dev server for the theme',
            action: 'dev',
            help: 'Example: pinx fe:dev --open',
        );
    }
}
