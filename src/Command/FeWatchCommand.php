<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\FeForwardOptions;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'fe:watch',
    description: 'Watch theme files and rebuild production assets',
)]
final class FeWatchCommand extends FeActionCommand
{
    public function __construct()
    {
        parent::__construct(
            name: 'fe:watch',
            description: 'Watch theme files and rebuild production assets',
            action: 'watch',
            help: 'Example: pinx fe:watch',
            forwardOptionNames: FeForwardOptions::watchForwardNames(),
        );
    }

    protected function configureOptions(): void
    {
        $this->addForwardOptions([
            ...FeForwardOptions::theme(),
            ...FeForwardOptions::install(),
            ['fix-vite', null, InputOption::VALUE_NONE, 'Auto-wire vite.config.js'],
            ['env-file', null, InputOption::VALUE_REQUIRED, 'Theme env file'],
        ]);
    }
}
