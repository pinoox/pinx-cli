<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

use Symfony\Component\Console\Input\InputOption;

/**
 * Option definitions forwarded to pincore theme:frontend (fe).
 */
final class FeForwardOptions
{
    /** @var list<string> */
    public const ACTIONS = ['info', 'install', 'build', 'dev', 'dev:apps', 'watch', 'run', 'scaffold'];

    /**
     * @return list<array{0: string, 1?: string|null, 2?: int, 3?: string}>
     */
    public static function theme(): array
    {
        return [
            ['theme', 't', InputOption::VALUE_REQUIRED, 'Theme folder name'],
        ];
    }

    /**
     * @return list<array{0: string, 1?: string|null, 2?: int, 3?: string}>
     */
    public static function scaffold(): array
    {
        return [
            ['stack', null, InputOption::VALUE_REQUIRED, 'Stack for scaffold: vue, react, twig'],
        ];
    }

    /**
     * @return list<array{0: string, 1?: string|null, 2?: int, 3?: string}>
     */
    public static function runScript(): array
    {
        return [
            ['script', null, InputOption::VALUE_REQUIRED, 'npm script name (run action)'],
        ];
    }

    /**
     * @return list<array{0: string, 1?: string|null, 2?: int, 3?: string}>
     */
    public static function install(): array
    {
        return [
            ['install', null, InputOption::VALUE_NONE, 'Force npm install'],
            ['no-install', null, InputOption::VALUE_NONE, 'Skip npm install'],
        ];
    }

    /**
     * @return list<array{0: string, 1?: string|null, 2?: int, 3?: string}>
     */
    public static function dev(): array
    {
        return [
            ['no-serve', null, InputOption::VALUE_NONE, 'Do not start PHP serve alongside dev'],
            ['serve-app', null, InputOption::VALUE_REQUIRED, 'PHP serve binding (package@path or platform)'],
            ['serve-host', null, InputOption::VALUE_REQUIRED, 'PHP serve host'],
            ['serve-port', null, InputOption::VALUE_REQUIRED, 'PHP serve port'],
            ['serve-domain', null, InputOption::VALUE_REQUIRED, 'Local hostname for browser URLs (SERVER_DOMAIN)'],
            ['domain', null, InputOption::VALUE_REQUIRED, 'Alias for --serve-domain'],
            ['network', 'N', InputOption::VALUE_NONE, 'Bind PHP + Vite on LAN (0.0.0.0)'],
            ['vite-host', null, InputOption::VALUE_REQUIRED, 'Vite bind host'],
            ['vite-network', null, InputOption::VALUE_NONE, 'Bind Vite to 0.0.0.0 for LAN'],
            ['verbose-vite', null, InputOption::VALUE_NONE, 'Show full Vite startup URLs'],
            ['fix-vite', null, InputOption::VALUE_NONE, 'Auto-wire vite.config.js with @pinooxhq/vite-plugin'],
            ['env-file', null, InputOption::VALUE_REQUIRED, 'Theme env file for dev auto-setup'],
            ['apps', null, InputOption::VALUE_REQUIRED, 'Comma-separated packages for dev:apps'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function basicForwardNames(): array
    {
        return ['theme', 'script', 'stack', 'install', 'no-install'];
    }

    /**
     * @return list<string>
     */
    public static function devForwardNames(): array
    {
        return [
            'theme',
            'install',
            'no-install',
            'no-serve',
            'serve-app',
            'serve-host',
            'serve-port',
            'serve-domain',
            'domain',
            'network',
            'vite-host',
            'vite-network',
            'verbose-vite',
            'fix-vite',
            'env-file',
        ];
    }

    /**
     * @return list<string>
     */
    public static function devAppsForwardNames(): array
    {
        return [...self::devForwardNames(), 'apps'];
    }

    /**
     * @return list<string>
     */
    public static function watchForwardNames(): array
    {
        return ['theme', 'install', 'no-install', 'fix-vite', 'env-file'];
    }
}
