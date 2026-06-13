<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

final class CorePath
{
    public static function resolve(string $projectRoot): string
    {
        $projectRoot = rtrim(str_replace('\\', '/', $projectRoot), '/');
        foreach ([
            $projectRoot . '/platform/launcher/core-path.php',
            $projectRoot . '/launcher/core-path.php',
        ] as $candidate) {
            if (is_file($candidate)) {
                require_once $candidate;

                return rtrim(pinoox_resolve_configured_core_path($projectRoot), '/');
            }
        }

        foreach ([
            $projectRoot . '/pincore',
            $projectRoot . '/vendor/pinoox/pincore',
        ] as $candidate) {
            if (is_file($candidate . '/functions/base.php') || is_file($candidate . '/launcher/bootstrap.php')) {
                return $candidate;
            }
        }

        return $projectRoot . '/vendor/pinoox/pincore';
    }

    public static function relativeLabel(string $projectRoot, string $corePath): string
    {
        $projectRoot = rtrim(str_replace('\\', '/', $projectRoot), '/');
        $corePath = rtrim(str_replace('\\', '/', $corePath), '/');

        if (str_starts_with($corePath, $projectRoot . '/')) {
            return ltrim(substr($corePath, strlen($projectRoot)), '/');
        }

        return $corePath;
    }
}
