<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

use Pinoox\PinxCli\Support\Manifest\ManifestLabel;

/**
 * Single-app Pinoox project context.
 *
 * A directory is recognized when it contains app.php with a non-empty "package" key
 * and composer.json requiring pinoox/pincore.
 */
final class AppContext
{
    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        public readonly string $root,
        public readonly string $package,
        public readonly array $config,
    ) {
    }

    public static function find(?string $start = null): ?self
    {
        $dir = $start ?? getcwd() ?: null;

        if ($dir === null) {
            return null;
        }

        $dir = ProjectRoot::normalize($dir);

        while ($dir !== '' && $dir !== '/') {
            $context = self::fromDirectory($dir);

            if ($context !== null) {
                return $context;
            }

            $parent = dirname($dir);

            if ($parent === $dir) {
                break;
            }

            $dir = $parent;
        }

        return null;
    }

    public static function require(?string $start = null): self
    {
        $context = self::find($start);

        if ($context === null) {
            throw new \RuntimeException(
                'Not inside a Pinoox single-app project. '
                . 'Expected app.php with a "package" key and pinoox/pincore in composer.json.',
            );
        }

        return $context;
    }

    public static function isAppRoot(string $dir): bool
    {
        return self::fromDirectory(ProjectRoot::normalize($dir)) !== null;
    }

    public function appPath(): string
    {
        return $this->root;
    }

    public function displayName(?string $locale = null): string
    {
        $locale ??= $this->locale();
        $paths = $this->langPaths();
        $fallbackLocale = $this->locale();

        foreach (['title', 'name'] as $field) {
            if (!array_key_exists($field, $this->config)) {
                continue;
            }

            $nameFallback = $this->config['name'] ?? $this->package;
            if (ManifestLabel::isLangRef($nameFallback) || ManifestLabel::isLocaleMap($nameFallback)) {
                $nameFallback = $this->package;
            }

            $fallback = $field === 'name' && is_string($nameFallback) ? $nameFallback : $this->package;
            $resolved = ManifestLabel::resolve(
                $this->config[$field],
                $paths,
                $locale,
                $fallback,
                $fallbackLocale,
            );

            if ($resolved !== '') {
                return $resolved;
            }
        }

        return $this->package;
    }

    public function description(?string $locale = null): string
    {
        $locale ??= $this->locale();

        if (!array_key_exists('description', $this->config)) {
            return '';
        }

        return ManifestLabel::resolve(
            $this->config['description'],
            $this->langPaths(),
            $locale,
            '',
            $this->locale(),
        );
    }

    public function locale(): string
    {
        $lang = $this->config['lang'] ?? 'en';

        return is_string($lang) && $lang !== '' ? $lang : 'en';
    }

    /**
     * @return list<string>
     */
    public function langPaths(): array
    {
        $path = $this->root . '/lang';

        return is_dir($path) ? [$path] : [];
    }

    public function theme(): ?string
    {
        $theme = $this->config['theme'] ?? null;

        return is_string($theme) && $theme !== '' ? $theme : null;
    }

    public function versionName(): ?string
    {
        $version = $this->config['version-name'] ?? null;

        return is_string($version) && $version !== '' ? $version : null;
    }

    public function versionCode(): ?int
    {
        $code = $this->config['version-code'] ?? null;

        return is_int($code) ? $code : (is_numeric($code) ? (int) $code : null);
    }

    public function iconRelativePath(): string
    {
        $icon = $this->config['icon'] ?? null;

        return is_string($icon) && $icon !== '' ? $icon : 'resource/icon.png';
    }

    public function iconPath(): string
    {
        return $this->root . '/' . $this->iconRelativePath();
    }

    /**
     * @return list<string>
     */
    public function routeFiles(): array
    {
        $routes = $this->config['router']['routes'] ?? [];

        if (!is_array($routes)) {
            return [];
        }

        $files = [];

        foreach ($routes as $routeFile) {
            if (is_string($routeFile) && $routeFile !== '') {
                $files[] = $routeFile;
            }
        }

        return $files;
    }

    private static function fromDirectory(string $dir): ?self
    {
        $appFile = $dir . '/app.php';

        if (!is_file($appFile)) {
            return null;
        }

        ProjectAutoload::boot($dir);

        $config = require $appFile;

        if (!is_array($config)) {
            return null;
        }

        $package = $config['package'] ?? null;

        if (!is_string($package) || trim($package) === '') {
            return null;
        }

        if (!self::hasPincore($dir)) {
            return null;
        }

        return new self($dir, trim($package), $config);
    }

    private static function hasPincore(string $dir): bool
    {
        if (is_dir($dir . '/pincore') && (is_file($dir . '/pincore/functions/base.php') || is_file($dir . '/pincore/launcher/bootstrap.php'))) {
            return true;
        }

        if (is_dir($dir . '/vendor/pinoox/pincore')) {
            return true;
        }

        $composerFile = $dir . '/composer.json';

        if (!is_file($composerFile)) {
            return false;
        }

        $composer = file_get_contents($composerFile);

        return is_string($composer) && str_contains($composer, 'pinoox/pincore');
    }
}
