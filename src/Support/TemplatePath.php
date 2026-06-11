<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

final class TemplatePath
{
    public static function skeletonDir(): string
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

        throw new \RuntimeException('pinoox/app template was not found next to pinx-cli.');
    }
}
