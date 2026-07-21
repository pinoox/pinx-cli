<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\AppContext;
use Pinoox\PinxCli\Support\PincoreActionCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'share',
    description: 'Interactive wizard to expose the app via a public tunnel',
)]
final class ShareCommand extends PincoreActionCommand
{
    public function __construct()
    {
        parent::__construct(
            name: 'share',
            description: 'Interactive wizard to expose the app via a public tunnel',
            defaultArgv: [],
            forwardOptionNames: [
                'provider',
                'share-provider',
                'mode',
                'network',
                'share-password',
                'share-expire',
                'host',
                'port',
                'domain',
                'target',
            ],
            help: <<<'HELP'
Step-by-step wizard: pick a tunnel provider, then start serve or dev with --share.

Examples:
  pinx share
  pinx share --share-provider=pinggy --mode=dev
  pinx share --mode=serve --network
  pinx share --mode=guide --provider=pinggy
  pinx share --share-password=secret --share-expire=2h

Default auto provider order:
  pinggy → serveo → cloudflare → localhostrun → bore → tunnelmole → ngrok → localtunnel
HELP,
        );
    }

    protected function configureOptions(): void
    {
        $this->addForwardOptions([
            ['provider', null, InputOption::VALUE_OPTIONAL, 'Tunnel provider (alias for --share-provider)'],
            ['share-provider', null, InputOption::VALUE_OPTIONAL, 'Tunnel provider: auto, pinggy, serveo, cloudflare, localhostrun, bore, tunnelmole, ngrok, localtunnel'],
            ['mode', null, InputOption::VALUE_OPTIONAL, 'Mode: serve (PHP server), dev (PHP + Vite HMR), or guide'],
            ['network', 'N', InputOption::VALUE_NONE, 'Listen on LAN (0.0.0.0)'],
            ['share-password', null, InputOption::VALUE_OPTIONAL, 'Protect the share URL with a password'],
            ['share-expire', null, InputOption::VALUE_OPTIONAL, 'Auto-stop the tunnel after a duration (e.g. 2h, 30m, 60s)'],
            ['host', null, InputOption::VALUE_OPTIONAL, 'Host address for serve/dev'],
            ['port', null, InputOption::VALUE_OPTIONAL, 'Port number for serve/dev'],
            ['domain', null, InputOption::VALUE_OPTIONAL, 'Local hostname for browser URLs'],
            ['target', null, InputOption::VALUE_OPTIONAL, 'Theme folder when dev mode needs an explicit target'],
        ]);
    }

    /**
     * @return list<string>
     */
    protected function pincoreArgv(AppContext $context, InputInterface $input): array
    {
        return ['share', '--app=' . $context->package];
    }
}
