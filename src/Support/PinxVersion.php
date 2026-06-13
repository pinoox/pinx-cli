<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

final class PinxVersion
{
    private const PACKAGE = 'pinoox/pinx-cli';

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
            $installed = \Composer\InstalledVersions::getPrettyVersion(self::PACKAGE);

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

    public static function installRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    public static function installMode(): string
    {
        if (self::isLocalSourceInstall()) {
            return 'local';
        }

        if (self::isGlobalInstall()) {
            return 'global';
        }

        if (self::projectRootFromInstall() !== null) {
            return 'project';
        }

        return 'unknown';
    }

    public static function installLabel(): string
    {
        return match (self::installMode()) {
            'local' => 'local source (' . self::shortenPath(self::installRoot()) . ')',
            'global' => 'global (' . self::shortenPath(self::installRoot()) . ')',
            'project' => 'project vendor (' . self::shortenPath(self::installRoot()) . ')',
            default => self::shortenPath(self::installRoot()),
        };
    }

    public static function isLocalSourceInstall(): bool
    {
        return is_dir(self::installRoot() . DIRECTORY_SEPARATOR . '.git');
    }

    public static function isGlobalInstall(): bool
    {
        $globalVendor = self::globalComposerVendor();

        if ($globalVendor === null) {
            return false;
        }

        $install = self::normalizedPath(realpath(self::installRoot()) ?: self::installRoot());
        $vendor = self::normalizedPath(realpath($globalVendor) ?: $globalVendor);

        return str_starts_with($install, rtrim($vendor, '/') . '/');
    }

    public static function projectRootFromInstall(): ?string
    {
        $root = realpath(self::installRoot()) ?: self::installRoot();
        $pattern = '#^(.*)[/\\\\]vendor[/\\\\]pinoox[/\\\\]pinx-cli$#';

        if (preg_match($pattern, $root, $matches) !== 1) {
            return null;
        }

        $projectRoot = $matches[1];

        return is_dir($projectRoot) ? $projectRoot : null;
    }

    public static function normalize(string $version): string
    {
        return ltrim($version, 'vV');
    }

    public static function isStable(string $version): bool
    {
        $normalized = strtolower(self::normalize($version));

        return !str_contains($normalized, 'dev')
            && !str_contains($normalized, 'alpha')
            && !str_contains($normalized, 'beta')
            && !str_contains($normalized, 'rc');
    }

    public static function isDevelopmentBuild(?string $version = null): bool
    {
        $version ??= self::version();

        return !self::isStable($version);
    }

    public static function shortenPath(string $path): string
    {
        $home = self::homeDirectory();

        if ($home !== null) {
            $normalizedHome = self::normalizedPath($home);
            $normalizedPath = self::normalizedPath($path);

            if (str_starts_with($normalizedPath, $normalizedHome . '/')) {
                return '~' . substr($normalizedPath, strlen($normalizedHome));
            }
        }

        return $path;
    }

    private static function readPackageComposerVersion(): ?string
    {
        $file = self::installRoot() . '/composer.json';

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

    private static function globalComposerVendor(): ?string
    {
        $home = self::globalComposerHome();

        if ($home === null) {
            return null;
        }

        $vendor = $home . DIRECTORY_SEPARATOR . 'vendor';

        return is_dir($vendor) ? $vendor : null;
    }

    private static function globalComposerHome(): ?string
    {
        $home = getenv('COMPOSER_HOME');

        if (is_string($home) && $home !== '') {
            return rtrim($home, '/\\');
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $appdata = getenv('APPDATA');

            if (is_string($appdata) && $appdata !== '') {
                $candidate = $appdata . DIRECTORY_SEPARATOR . 'Composer';

                if (is_dir($candidate)) {
                    return $candidate;
                }
            }

            $localAppData = getenv('LOCALAPPDATA');

            if (is_string($localAppData) && $localAppData !== '') {
                $candidate = $localAppData . DIRECTORY_SEPARATOR . 'Composer';

                if (is_dir($candidate)) {
                    return $candidate;
                }
            }

            return null;
        }

        $userHome = self::homeDirectory();

        if ($userHome === null) {
            return null;
        }

        foreach ([
            $userHome . DIRECTORY_SEPARATOR . '.config' . DIRECTORY_SEPARATOR . 'composer',
            $userHome . DIRECTORY_SEPARATOR . '.composer',
        ] as $candidate) {
            if (is_dir($candidate)) {
                return $candidate;
            }
        }

        return $userHome . DIRECTORY_SEPARATOR . '.composer';
    }

    private static function homeDirectory(): ?string
    {
        foreach (['HOME', 'USERPROFILE'] as $key) {
            $value = getenv($key);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private static function normalizedPath(string $path): string
    {
        return str_replace('\\', '/', $path);
    }
}
