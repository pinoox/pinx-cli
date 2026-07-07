<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\FeForwardOptions;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'fe:dev',
    description: 'Start PHP + Vite dev with HMR for the theme',
)]
final class FeDevCommand extends FeActionCommand
{
    public function __construct()
    {
        parent::__construct(
            name: 'fe:dev',
            description: 'Start PHP + Vite dev with HMR for the theme',
            action: 'dev',
            help: <<<'HELP'
Starts PHP serve + Vite HMR (waits until Vite is ready, then shows URLs).

Open the PHP URL in the terminal output — not http://127.0.0.1:5173.

Examples:
  pinx fe:dev
  pinx fe:dev --network
  pinx fe:dev --no-serve
  pinx fe:dev --fix-vite --install
HELP,
            forwardOptionNames: FeForwardOptions::devForwardNames(),
        );
    }

    protected function configureOptions(): void
    {
        $this->addForwardOptions([
            ...FeForwardOptions::theme(),
            ...FeForwardOptions::install(),
            ...FeForwardOptions::dev(),
        ]);
    }
}
