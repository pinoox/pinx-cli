<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\FeForwardOptions;
use Pinoox\PinxCli\Support\RunsForApp;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'dev',
    description: 'Start PHP + Vite dev (HMR) for the app theme',
)]
final class DevCommand extends Command
{
    use RunsForApp;

    /** @var list<string> */
    private const SERVE_FORWARD = [
        'host',
        'port',
        'domain',
        'network',
        'tries',
        'no-reload',
        'no-inspector',
        'open-inspector',
        'open',
        'share',
        'share-password',
        'share-expire',
        'share-provider',
    ];

    /** @var list<string> */
    private const DEV_FORWARD = [
        'theme',
        'install',
        'no-install',
        'no-serve',
        'network',
        'serve-host',
        'serve-port',
        'vite-host',
        'vite-network',
        'verbose-vite',
        'fix-vite',
        'env-file',
        'no-inspector',
        'open-inspector',
        'share',
        'share-password',
        'share-expire',
        'share-provider',
    ];

    protected function configure(): void
    {
        $this
            ->setHelp(
                <<<'HELP'
Starts PHP serve + Vite HMR for the active app theme (same as php pinoox dev / fe dev).

Open the PHP URL shown in the terminal — not the Vite port (:5173).
Use `pinx serve` (or `php pinoox serve --app=…`) when you want built manifest assets only.

Examples:
  pinx dev
  pinx dev --domain=pinoox.test
  pinx dev --port=8080
  pinx dev --no-frontend
  pinx dev --network
  pinx dev --theme=panel
  pinx dev --share
  pinx dev -N --share
  pinx dev --share --share-password=123456
  pinx dev --share --share-expire=2h
HELP
            )
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'PHP serve host (alias for --serve-host)')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'PHP serve port (alias for --serve-port)')
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Local hostname (alias for --serve-domain)')
            ->addOption('no-frontend', null, InputOption::VALUE_NONE, 'PHP serve only (manifest assets, no Vite)')
            ->addOption('network', 'N', InputOption::VALUE_NONE, 'Bind PHP + Vite on LAN (0.0.0.0)')
            ->addOption('no-inspector', null, InputOption::VALUE_NONE, 'Disable Pinx Inspector on /~inspector')
            ->addOption('open-inspector', null, InputOption::VALUE_NONE, 'Open Pinx Inspector in the browser')
            ->addOption('open', 'o', InputOption::VALUE_NONE, 'Open browser after PHP serve starts (--no-frontend only)')
            ->addOption('share', null, InputOption::VALUE_NONE, 'Expose the server via a public tunnel (Cloudflare, Pinggy, ngrok, …)')
            ->addOption('share-provider', null, InputOption::VALUE_OPTIONAL, 'Tunnel provider: auto, pinggy, serveo, cloudflare, localhostrun, bore, tunnelmole, ngrok, localtunnel')
            ->addOption('share-password', null, InputOption::VALUE_OPTIONAL, 'Protect the share URL with a password')
            ->addOption('share-expire', null, InputOption::VALUE_OPTIONAL, 'Auto-stop the tunnel after a duration (e.g. 2h, 30m, 60s)');

        $this->addForwardOptions([
            ...FeForwardOptions::theme(),
            ...FeForwardOptions::install(),
            ...FeForwardOptions::dev(),
        ]);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $context = $this->requireApp($io);

        if ($context === null) {
            return Command::FAILURE;
        }

        if ($input->getOption('no-frontend')) {
            $args = array_merge(
                ['serve', '--app=' . $context->package],
                $this->forwardOptions($input, self::SERVE_FORWARD),
            );

            return $this->runPincore($context, $args, $output, ['PINOOX_VITE_HMR' => '0']);
        }

        $args = ['dev', $context->package];
        $args = array_merge($args, $this->mappedServeAliases($input), $this->forwardOptions($input, self::DEV_FORWARD));

        return $this->runPincore($context, $args, $output, ['PINOOX_VITE_HMR' => '1']);
    }

    /**
     * @return list<string>
     */
    private function mappedServeAliases(InputInterface $input): array
    {
        $args = [];
        $host = $input->getOption('host');
        $port = $input->getOption('port');

        if (is_string($host) && trim($host) !== '' && !$input->getOption('serve-host')) {
            $args[] = '--serve-host=' . trim($host);
        }

        if (is_string($port) && trim($port) !== '' && !$input->getOption('serve-port')) {
            $args[] = '--serve-port=' . trim($port);
        }

        $domain = $input->getOption('domain');

        if (is_string($domain) && trim($domain) !== '' && !$input->getOption('serve-domain')) {
            $args[] = '--serve-domain=' . trim($domain);
        }

        return $args;
    }

    private function addForwardOptions(array $definitions): void
    {
        foreach ($definitions as $definition) {
            if (!is_array($definition) || !isset($definition[0])) {
                continue;
            }

            $this->addOption(
                (string) $definition[0],
                $definition[1] ?? null,
                $definition[2] ?? InputOption::VALUE_NONE,
                $definition[3] ?? '',
            );
        }
    }
}
