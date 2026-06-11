<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'pinker:diff', description: 'Show differences between Pinker cache and source')]
final class PinkerDiffCommand extends PinkerActionCommand
{
    public function __construct()
    {
        parent::__construct('pinker:diff', 'Show differences between Pinker cache and source', 'diff', 'Example: pinx pinker:diff');
    }
}
