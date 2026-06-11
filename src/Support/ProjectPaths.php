<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

final class ProjectPaths
{
    public static function platformConfigDir(string $projectRoot): string
    {
        $projectRoot = ProjectRoot::normalize($projectRoot);
        $override = getenv('PINOOX_PROJECT_CONFIG_PATH');

        if (is_string($override) && $override !== '') {
            return self::resolveProjectRelativePath($projectRoot, $override);
        }

        if (is_dir($projectRoot . '/platform')) {
            return $projectRoot . '/platform';
        }

        return $projectRoot . '/config';
    }

    public static function appsRegistryFile(string $projectRoot): string
    {
        $projectRoot = ProjectRoot::normalize($projectRoot);
        $override = getenv('PINOOX_PROJECT_REGISTRY_PATH');

        if (is_string($override) && $override !== '') {
            $resolved = self::resolveProjectRelativePath($projectRoot, $override);

            return str_ends_with($resolved, '.php') ? $resolved : $resolved . '/apps.config.php';
        }

        foreach ([
            $projectRoot . '/platform/apps.config.php',
            $projectRoot . '/config/apps.config.php',
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return $projectRoot . '/platform/apps.config.php';
    }

    public static function appsRegistryRelativeLabel(string $projectRoot): string
    {
        $file = self::appsRegistryFile($projectRoot);
        $root = ProjectRoot::normalize($projectRoot);

        return str_starts_with($file, $root . '/')
            ? substr($file, strlen($root) + 1)
            : $file;
    }

    private static function resolveProjectRelativePath(string $projectRoot, string $path): string
    {
        $path = trim(str_replace('\\', '/', $path));

        if ($path === '~' || $path === '~/' || $path === '') {
            return $projectRoot;
        }

        if (str_starts_with($path, '~/')) {
            return ProjectRoot::normalize($projectRoot . '/' . substr($path, 2));
        }

        if (preg_match('/^[A-Za-z]:\//', $path) === 1 || str_starts_with($path, '/')) {
            return ProjectRoot::normalize($path);
        }

        return ProjectRoot::normalize($projectRoot . '/' . $path);
    }
}
