<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'fe:scaffold', description: 'Scaffold starter frontend files for the theme')]
final class FeScaffoldCommand extends FeActionCommand
{
    public function __construct()
    {
        parent::__construct(
            name: 'fe:scaffold',
            description: 'Scaffold starter frontend files for the theme',
            action: 'scaffold',
            help: 'Example: pinx fe:scaffold --stack=vue',
        );
    }
}
