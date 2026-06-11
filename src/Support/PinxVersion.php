<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

final class PinxVersion
{
    private static ?string $resolved = null;

    public static function version(): string
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        $fromComposer = self::readPackageComposerVersion();

        if ($fromComposer !== null) {
            return self::$resolved = $fromComposer;
        }

        if (class_exists(\Composer\InstalledVersions::class)) {
            $installed = \Composer\InstalledVersions::getPrettyVersion('pinoox/pinx-cli');

            if (is_string($installed) && $installed !== '') {
                return self::$resolved = $installed;
            }
        }

        return self::$resolved = 'dev';
    }

    public static function label(): string
    {
        return 'pinx ' . self::version();
    }

    private static function readPackageComposerVersion(): ?string
    {
        $file = dirname(__DIR__, 2) . '/composer.json';

        if (!is_file($file)) {
            return null;
        }

        $json = json_decode((string) file_get_contents($file), true);

        if (!is_array($json)) {
            return null;
        }

        $version = $json['version'] ?? null;

        return is_string($version) && $version !== '' ? $version : null;
    }
}
