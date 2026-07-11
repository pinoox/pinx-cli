<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\AppContext;
use Pinoox\PinxCli\Support\PincoreActionCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'serve',
    description: 'Start the PHP development web server for the app',
)]
final class ServeCommand extends PincoreActionCommand
{
    public function __construct()
    {
        parent::__construct(
            name: 'serve',
            description: 'Start the PHP development web server for the app',
            defaultArgv: [],
            forwardOptionNames: [
                'host',
                'port',
                'domain',
                'network',
                'tries',
                'no-reload',
                'no-inspector',
                'open-inspector',
                'open',
            ],
            help: <<<'HELP'
Starts the PHP built-in server locked to the current app (same as php pinoox serve --app=…).

Examples:
  pinx serve
  pinx serve --port=8080
  pinx serve --network
  pinx serve --open
HELP,
        );
    }

    protected function configureOptions(): void
    {
        $this->addForwardOptions([
            ['host', null, InputOption::VALUE_OPTIONAL, 'Host address (default 127.0.0.1; use --network for 0.0.0.0)'],
            ['port', null, InputOption::VALUE_OPTIONAL, 'Port number (auto-picks next free port when busy)'],
            ['domain', null, InputOption::VALUE_OPTIONAL, 'Local hostname for browser URLs (requires hosts file entry)'],
            ['network', 'N', InputOption::VALUE_NONE, 'Listen on 0.0.0.0 and show LAN URL'],
            ['tries', null, InputOption::VALUE_OPTIONAL, 'How many ports to try if the default is busy', 10],
            ['no-reload', null, InputOption::VALUE_NONE, 'Do not restart when .env changes'],
            ['no-inspector', null, InputOption::VALUE_NONE, 'Disable Pinx Inspector on /~inspector'],
            ['open-inspector', null, InputOption::VALUE_NONE, 'Open Pinx Inspector in the browser'],
            ['open', 'o', InputOption::VALUE_NONE, 'Open the site in your default browser after start'],
        ]);
    }

    /**
     * @return list<string>
     */
    protected function pincoreArgv(AppContext $context, InputInterface $input): array
    {
        return ['serve', '--app=' . $context->package];
    }
}
