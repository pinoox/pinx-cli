<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\AppContext;
use Pinoox\PinxCli\Support\RunsForApp;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'info',
    description: 'Show current app metadata from app.php',
    aliases: ['app:info'],
)]
final class InfoCommand extends Command
{
    use RunsForApp;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = $this->requireApp($io);

        if ($context === null) {
            return Command::FAILURE;
        }

        $config = $context->config;
        $rows = [
            ['Package', $context->package],
            ['Name', $context->displayName()],
            ['Path', $context->appPath()],
            ['Theme', $context->theme() ?? '—'],
            ['Version', $this->formatVersion($context)],
            ['Enabled', $this->formatBool($config['enable'] ?? true)],
            ['Language', (string) ($config['lang'] ?? '—')],
            ['Developer', (string) ($config['developer'] ?? '—')],
            ['Description', $context->description() !== '' ? $context->description() : '—'],
        ];

        $frontend = $config['frontend']['stack'] ?? null;
        if (is_string($frontend) && $frontend !== '') {
            $rows[] = ['Frontend', $frontend];
        }

        $routes = $config['router']['routes'] ?? [];
        if (is_array($routes) && $routes !== []) {
            $rows[] = ['Route files', implode(', ', array_map('strval', $routes))];
        }

        $io->title('Pinoox app info');
        $io->table(['Key', 'Value'], $rows);
        $this->renderLayout($io, $context);

        return Command::SUCCESS;
    }

    private function formatVersion(AppContext $context): string
    {
        $name = $context->versionName();
        $code = $context->versionCode();

        if ($name === null && $code === null) {
            return '—';
        }

        if ($name !== null && $code !== null) {
            return $name . ' #' . $code;
        }

        return $name ?? ('#' . $code);
    }

    private function formatBool(mixed $value): string
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'yes' : 'no';
    }

    private function renderLayout(SymfonyStyle $io, AppContext $context): void
    {
        $root = $context->appPath();
        $checks = [
            'Controller' => $root . '/Controller',
            'Model' => $root . '/Model',
            'Routes' => $root . '/routes',
            'Migrations' => $root . '/database/migrations',
            'Seeders' => $root . '/database/seed',
            'Patches' => $root . '/patches',
            'Portal' => $root . '/Portal',
            'Request' => $root . '/Request',
            'Flow' => $root . '/Flow',
            'Resource' => $root . '/resource',
            'Theme' => $context->theme() ? $root . '/theme/' . $context->theme() : null,
            'Tests' => $root . '/tests',
            'Pinker' => $root . '/pinker',
            'Schedule' => $root . '/schedule.php',
        ];

        $rows = [];

        foreach ($checks as $label => $path) {
            if ($path === null) {
                continue;
            }

            $exists = is_dir($path) || is_file($path);
            $rows[] = [$label, $exists ? 'yes' : 'no', str_replace($root . '/', '', $path)];
        }

        $io->section('App layout');
        $io->table(['Section', 'Present', 'Path'], $rows);
    }
}
