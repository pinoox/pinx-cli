<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

/**
 * Converts pinx-only command names to commands exposed by php pinoox.
 */
final class PlatformCommandArguments
{
    /**
     * @var array<string, list<string>>
     */
    private const COMMANDS = [
        'deps:status' => ['deps', 'status'],
        'deps:st' => ['deps', 'status'],
        'deps:install' => ['deps', 'install'],
        'deps:i' => ['deps', 'install'],
        'deps:update' => ['deps', 'update'],
        'deps:up' => ['deps', 'update'],
    ];

    /**
     * @param list<string> $args
     * @return list<string>
     */
    public static function normalize(array $args): array
    {
        foreach ($args as $index => $arg) {
            if (str_starts_with($arg, '-')) {
                continue;
            }

            if (isset(self::COMMANDS[$arg])) {
                array_splice($args, $index, 1, self::COMMANDS[$arg]);
            }

            break;
        }

        return array_values($args);
    }
}
