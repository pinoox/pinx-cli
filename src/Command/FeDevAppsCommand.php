<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\FeForwardOptions;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'fe:dev:apps',
    description: 'Start platform PHP serve + Vite HMR for multiple apps',
)]
final class FeDevAppsCommand extends FeActionCommand
{
    public function __construct()
    {
        parent::__construct(
            name: 'fe:dev:apps',
            description: 'Start platform PHP serve + Vite HMR for multiple apps',
            action: 'dev:apps',
            aliases: ['fe:dev-apps'],
            help: <<<'HELP'
Starts one shared PHP serve plus parallel Vite dev servers (platform router).

Examples:
  pinx fe:dev:apps
  pinx fe:dev:apps --apps=com_pinoox_manager,com_pinoox_welcome
HELP,
            forwardOptionNames: FeForwardOptions::devAppsForwardNames(),
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
