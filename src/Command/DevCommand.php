<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\DevApp;
use Pinoox\PinxCli\Support\Inspector\InspectorServer;
use Pinoox\PinxCli\Support\PincoreRunner;
use Pinoox\PinxCli\Support\ProjectRoot;
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
    protected function configure(): void
    {
        $this
            ->setHelp(
                <<<'HELP'
Starts PHP serve + Vite HMR for the active app theme (same as pincore `dev` / `fe dev`).

Open the PHP URL shown in the terminal — not the Vite port (:5173).
Use `pinx serve` (or `php pinoox serve`) when you want built manifest assets only.

Examples:
  pinx dev
  pinx dev --port=8080
  pinx dev --no-frontend
  pinx dev --network
HELP
            )
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'PHP serve host')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'PHP serve port')
            ->addOption('no-frontend', null, InputOption::VALUE_NONE, 'PHP serve only (manifest assets, no Vite)')
            ->addOption('network', 'N', InputOption::VALUE_NONE, 'Bind PHP + Vite on LAN (0.0.0.0)')
            ->addOption('no-inspector', null, InputOption::VALUE_NONE, 'Disable Pinx Inspector on /~inspector')
            ->addOption('open-inspector', null, InputOption::VALUE_NONE, 'Open Pinx Inspector in the browser')
            ->addOption('open', 'o', InputOption::VALUE_NONE, 'Open browser after PHP serve starts (--no-frontend only)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $root = ProjectRoot::require();
            $package = DevApp::requirePackage($root);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $runner = new PincoreRunner($root);
        $host = (string) ($input->getOption('host') ?: getenv('SERVER_HOST') ?: '127.0.0.1');
        $port = (string) ($input->getOption('port') ?: getenv('SERVER_PORT') ?: '8000');
        $extraEnv = [];
        $inspectorUrl = 'http://' . $host . ':' . $port . '/~inspector';

        if (!$input->getOption('no-inspector')) {
            try {
                $inspector = new InspectorServer();
                $extraEnv = [
                    'PINX_INSPECTOR_ENABLED' => '1',
                    'PINX_INSPECTOR_ROUTE' => '/~inspector',
                    'PINX_INSPECTOR_ROUTER' => $inspector->router($root),
                    'PINX_INSPECTOR_WIDGET' => '1',
                    'PINX_INSPECTOR_PROJECT_ROOT' => $root,
                ];
                $io->note('Pinx Inspector: ' . $inspectorUrl);

                if ($input->getOption('open-inspector')) {
                    $inspector->openBrowser($inspectorUrl);
                }
            } catch (\Throwable $e) {
                $io->warning('Pinx Inspector could not start: ' . $e->getMessage());
            }
        }

        if (!$input->getOption('no-frontend') && $this->usesViteFrontend($root)) {
            $args = [
                'dev',
                $package,
                '--serve-host=' . $host,
                '--serve-port=' . $port,
            ];

            if ($input->getOption('network')) {
                $args[] = '--network';
            }

            $extraEnv['PINOOX_VITE_HMR'] = '1';

            return $runner->run($args, $output, $extraEnv);
        }

        $args = ['serve', '--app=' . $package, '--host=' . $host, '--port=' . $port];

        if ($input->getOption('open')) {
            $args[] = '--open';
        }

        $extraEnv['PINOOX_VITE_HMR'] = '0';

        return $runner->run($args, $output, $extraEnv);
    }

    private function usesViteFrontend(string $root): bool
    {
        $stack = $this->frontendStack($root);

        if ($stack === null || $stack === '' || $stack === 'none' || $stack === 'twig') {
            return false;
        }

        return true;
    }

    private function frontendStack(string $root): ?string
    {
        $appFile = $root . '/app.php';

        if (!is_file($appFile)) {
            return null;
        }

        $config = require $appFile;

        if (!is_array($config)) {
            return null;
        }

        $stack = $config['frontend']['stack'] ?? null;

        return is_string($stack) ? $stack : null;
    }
}
