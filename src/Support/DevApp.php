<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

final class DevApp
{
    public static function package(string $projectRoot): ?string
    {
        $fromEnv = getenv('PINX_PACKAGE') ?: getenv('PINOOX_DEV_APP') ?: null;

        if (is_string($fromEnv) && $fromEnv !== '') {
            return trim($fromEnv);
        }

        $context = AppContext::find($projectRoot);

        if ($context !== null) {
            return $context->package;
        }

        $fromRegistry = self::fromAppsConfig($projectRoot);
        if ($fromRegistry !== null) {
            return $fromRegistry;
        }

        return self::fromRootAppFile($projectRoot);
    }

    public static function requirePackage(string $projectRoot): string
    {
        $context = AppContext::find($projectRoot);

        if ($context !== null) {
            return $context->package;
        }

        $package = self::package($projectRoot);

        if ($package === null || $package === '') {
            throw new \RuntimeException('Could not detect app package. Add app.php with a "package" key at the project root or set PINX_PACKAGE in .env.');
        }

        return $package;
    }

    public static function pincoreEnv(string $projectRoot): array
    {
        $env = $_ENV;
        $package = self::package($projectRoot);

        if ($package !== null && $package !== '') {
            $env['PINX_PACKAGE'] = $package;
            $env['PINOOX_DEV_APP'] = $package;
            $env['SERVER_APP'] = $package;
        }

        $env['PINX_DEV'] = '1';

        return $env;
    }

    private static function fromRootAppFile(string $projectRoot): ?string
    {
        $appFile = $projectRoot . '/app.php';

        if (!is_file($appFile)) {
            return null;
        }

        $config = require $appFile;

        if (!is_array($config)) {
            return null;
        }

        $package = $config['package'] ?? null;

        return is_string($package) && $package !== '' ? $package : null;
    }

    private static function fromAppsConfig(string $projectRoot): ?string
    {
        $registryFile = ProjectPaths::appsRegistryFile($projectRoot);

        if (!is_file($registryFile)) {
            return null;
        }

        $config = require $registryFile;

        if (!is_array($config)) {
            return null;
        }

        $packages = $config['packages'] ?? $config['apps'] ?? [];

        if (!is_array($packages)) {
            return null;
        }

        foreach ($packages as $package => $definition) {
            if (!is_string($package)) {
                continue;
            }

            $path = is_string($definition)
                ? $definition
                : (is_array($definition) ? ($definition['path'] ?? null) : null);

            if (!is_string($path)) {
                continue;
            }

            if ($path === '~' || $path === '~/') {
                return $package;
            }
        }

        return null;
    }
}
