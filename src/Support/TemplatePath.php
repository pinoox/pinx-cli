<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

use Symfony\Component\Console\Output\OutputInterface;

final class TemplatePath
{
    public const TEMPLATE_PACKAGE = 'pinoox/app';

    public static function hasLocal(): bool
    {
        return self::localDir() !== null;
    }

    public static function skeletonDir(): string
    {
        $local = self::localDir();

        if ($local !== null) {
            return $local;
        }

        throw new \RuntimeException(
            'pinoox/app template was not found next to pinx-cli. '
            . 'Run pinx new (uses composer create-project) or install pinoox/app from Packagist.',
        );
    }

    public static function resolve(?OutputInterface $output = null): string
    {
        $local = self::localDir();

        if ($local !== null) {
            return $local;
        }

        return self::downloadToCache($output);
    }

    public static function sourceLabel(): string
    {
        return self::hasLocal()
            ? self::skeletonDir()
            : self::TEMPLATE_PACKAGE . ' (via Composer)';
    }

    private static function localDir(): ?string
    {
        $candidates = [
            dirname(__DIR__, 3) . '/app',
            dirname(__DIR__, 2) . '/resources/app',
        ];

        foreach ($candidates as $candidate) {
            if (is_dir($candidate) && is_file($candidate . '/composer.json')) {
                return ProjectRoot::normalize($candidate);
            }
        }

        return null;
    }

    private static function downloadToCache(?OutputInterface $output): string
    {
        $cache = self::cacheDir();

        if (is_dir($cache) && is_file($cache . '/app.php') && is_file($cache . '/composer.json')) {
            return $cache;
        }

        if (is_dir($cache)) {
            self::removeDirectory($cache);
        }

        $sink = $output ?? new \Symfony\Component\Console\Output\NullOutput();
        $code = ComposerRunner::run(
            ['create-project', self::TEMPLATE_PACKAGE, $cache, '--no-install', '--no-interaction'],
            null,
            $sink,
        );

        if ($code !== 0 || !is_file($cache . '/app.php')) {
            self::removeDirectory($cache);

            throw new \RuntimeException(
                'Failed to download ' . self::TEMPLATE_PACKAGE . ' from Packagist. '
                . 'Check your network connection and that the package is published.',
            );
        }

        return ProjectRoot::normalize($cache);
    }

    private static function cacheDir(): string
    {
        return ProjectRoot::normalize(rtrim(sys_get_temp_dir(), '/\\') . '/pinx-template-pinoox-app');
    }

    private static function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if ($item->isDir()) {
                rmdir($item->getPathname());
                continue;
            }

            unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
