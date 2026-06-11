<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

final class ProjectRoot
{
    public static function find(?string $start = null): ?string
    {
        $dir = $start ?? getcwd() ?: null;

        if ($dir === null) {
            return null;
        }

        $dir = self::normalize($dir);

        while ($dir !== '' && $dir !== '/') {
            if (self::isProjectRoot($dir)) {
                return $dir;
            }

            $parent = dirname($dir);
            if ($parent === $dir) {
                break;
            }

            $dir = $parent;
        }

        return null;
    }

    public static function require(?string $start = null): string
    {
        $context = AppContext::find($start);

        if ($context !== null) {
            return $context->root;
        }

        $root = self::find($start);

        if ($root === null) {
            throw new \RuntimeException(
                'Not inside a Pinoox single-app project. '
                . 'Expected app.php with a "package" key and pinoox/pincore in composer.json.',
            );
        }

        return $root;
    }

    private static function isProjectRoot(string $dir): bool
    {
        if (AppContext::isAppRoot($dir)) {
            return true;
        }

        if (!is_file($dir . '/composer.json')) {
            return false;
        }

        $composer = file_get_contents($dir . '/composer.json');

        if (!is_string($composer)) {
            return false;
        }

        return str_contains($composer, 'pinoox/pincore');
    }

    public static function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
