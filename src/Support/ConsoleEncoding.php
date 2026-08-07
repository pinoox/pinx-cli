<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

final class ConsoleEncoding
{
    private const UTF8_CODEPAGE = 65001;

    public static function bootUtf8(): void
    {
        if (PHP_SAPI !== 'cli') {
            return;
        }

        CliTerminalStyle::boot();

        ini_set('default_charset', 'UTF-8');

        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding('UTF-8');
        }

        self::ensureUtf8();
    }

    public static function ensureUtf8(): void
    {
        if (PHP_OS_FAMILY !== 'Windows' || !function_exists('sapi_windows_cp_set')) {
            return;
        }

        if (self::isUtf8Console()) {
            return;
        }

        @sapi_windows_cp_set(self::UTF8_CODEPAGE);
    }

    public static function isUtf8Console(): bool
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return true;
        }

        if (function_exists('sapi_windows_cp_is_utf8')) {
            return sapi_windows_cp_is_utf8();
        }

        return function_exists('sapi_windows_cp_get')
            && sapi_windows_cp_get() === self::UTF8_CODEPAGE;
    }

    /**
     * Prefer ASCII UI when the Windows console cannot reliably render UTF-8 box drawing.
     */
    public static function prefersAsciiUi(): bool
    {
        $unicode = strtolower((string) (getenv('PINOOX_CLI_UNICODE') ?: ($_SERVER['PINOOX_CLI_UNICODE'] ?? '')));
        if (in_array($unicode, ['1', 'true', 'yes', 'on'], true)) {
            return false;
        }

        if (in_array($unicode, ['0', 'false', 'no', 'off'], true)) {
            return true;
        }

        $ascii = strtolower((string) (getenv('PINOOX_CLI_ASCII') ?: ($_SERVER['PINOOX_CLI_ASCII'] ?? '')));
        if (in_array($ascii, ['1', 'true', 'yes', 'on'], true)) {
            return true;
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            return false;
        }

        self::ensureUtf8();

        // Windows Terminal renders Unicode reliably; classic/OEM and many IDE hosts do not.
        return getenv('WT_SESSION') === false || getenv('WT_SESSION') === '';
    }

    /**
     * @param list<string> $args
     * @return list<string>
     */
    public static function withAsciiUiArgs(array $args): array
    {
        if (!self::prefersAsciiUi()) {
            return $args;
        }

        if (!in_array('--plain', $args, true) && self::isDepsArgv($args)) {
            $args[] = '--plain';
        }

        return $args;
    }

    /**
     * @return array<string, string>
     */
    public static function processEnv(): array
    {
        $env = [];

        if (self::prefersAsciiUi()) {
            $env['PINOOX_CLI_ASCII'] = '1';
        }

        return $env;
    }

    /**
     * @param list<string> $args
     */
    private static function isDepsArgv(array $args): bool
    {
        foreach ($args as $arg) {
            if ($arg === 'deps' || $arg === 'dep' || str_starts_with($arg, 'deps:')) {
                return true;
            }
        }

        return false;
    }
}
