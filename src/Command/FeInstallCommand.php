<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'fe:install', description: 'Run npm install for the active theme')]
final class FeInstallCommand extends FeActionCommand
{
    public function __construct()
    {
        parent::__construct(
            name: 'fe:install',
            description: 'Run npm install for the active theme',
            action: 'install',
            help: 'Example: pinx fe:install --theme=all (or omit --theme for interactive pick)',
        );
    }
}
