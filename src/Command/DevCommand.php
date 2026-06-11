<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\DevApp;
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
    description: 'Start the development server (and Vite when the app uses a frontend stack)',
)]
final class DevCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Server host')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Server port')
            ->addOption('no-frontend', null, InputOption::VALUE_NONE, 'Skip Vite/npm dev')
            ->addOption('open', 'o', InputOption::VALUE_NONE, 'Open browser after start');
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
        $stack = $this->frontendStack($root);

        if (!$input->getOption('no-frontend') && $stack !== null && $stack !== 'none' && $stack !== 'twig') {
            $args = ['fe', $package, 'dev', '--serve-host=' . $host, '--serve-port=' . $port];
            if ($input->getOption('open')) {
                $args[] = '--open';
            }

            $io->note('Starting Vite + PHP server for ' . $package);

            return $runner->run($args, $output);
        }

        $args = ['serve', '--app=' . $package, '--host=' . $host, '--port=' . $port];
        if ($input->getOption('open')) {
            $args[] = '--open';
        }

        $io->note('Starting PHP server for ' . $package . ' at http://' . $host . ':' . $port);

        return $runner->run($args, $output);
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
