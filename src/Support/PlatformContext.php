<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

/**
 * Detects a multi-app Pinoox platform (apps/{package}/…) vs a single-app pinx project.
 */
final class PlatformContext
{
    public function __construct(
        public readonly string $root,
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
            if (AppContext::isAppRoot($dir)) {
                return null;
            }

            if (self::isPlatformRoot($dir)) {
                return new self($dir);
            }

            $parent = dirname($dir);

            if ($parent === $dir) {
                break;
            }

            $dir = $parent;
        }

        return null;
    }

    public function pinooxScript(): string
    {
        foreach ([
            $this->root . '/pinoox',
            $this->root . '/platform/launcher/bootstrap.php',
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $this->root . '/pinoox';
    }

    public function invokeLabel(): string
    {
        $script = $this->pinooxScript();
        $relative = str_starts_with($script, $this->root . '/')
            ? ltrim(substr($script, strlen($this->root)), '/')
            : basename($script);

        return $relative === 'pinoox'
            ? 'php pinoox'
            : 'php ' . $relative;
    }

    /**
     * Commands that must stay on pinx even inside a platform tree.
     *
     * @param list<string> $argvArgs arguments after the script name
     */
    public static function isPinxLocalCommand(array $argvArgs): bool
    {
        if ($argvArgs === []) {
            return false;
        }

        $first = strtolower(ltrim((string) $argvArgs[0], '-'));

        return in_array($first, ['new'], true);
    }

    private static function isPlatformRoot(string $dir): bool
    {
        if (!is_dir($dir . '/apps')) {
            return false;
        }

        if (!self::hasAppPackage($dir . '/apps')) {
            return false;
        }

        return is_file($dir . '/pinoox')
            || is_file($dir . '/platform/launcher/bootstrap.php');
    }

    private static function hasAppPackage(string $appsPath): bool
    {
        if (!is_dir($appsPath)) {
            return false;
        }

        foreach (scandir($appsPath) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (is_file($appsPath . '/' . $entry . '/app.php')) {
                return true;
            }
        }

        return false;
    }
}
