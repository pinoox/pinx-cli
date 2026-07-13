<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'fe:build', description: 'Build production frontend assets for the theme')]
final class FeBuildCommand extends FeActionCommand
{
    public function __construct()
    {
        parent::__construct(
            name: 'fe:build',
            description: 'Build production frontend assets for the theme',
            action: 'build',
            help: 'Example: pinx fe:build --theme=all (or omit --theme for interactive pick)',
        );
    }
}
