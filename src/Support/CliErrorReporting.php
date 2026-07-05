<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

final class CliErrorReporting
{
    private const DEV_MODES = ['development', 'dev', 'local', 'test'];

    private static bool $booted = false;

    public static function boot(?string $projectRoot = null): void
    {
        if (PHP_SAPI !== 'cli' || self::$booted) {
            return;
        }

        self::$booted = true;
        self::quietPhpOutput();

        if (!self::shouldDisplayErrors($projectRoot)) {
            return;
        }

        self::registerExceptionOnlyHandler();
    }

    /**
     * @return list<string>
     */
    public static function phpIniArgs(?string $projectRoot = null): array
    {
        $args = self::quietPhpIniArgs();

        if (self::shouldDisplayErrors($projectRoot)) {
            $args[] = '-d';
            $args[] = 'error_reporting=' . self::errorReportingLevel();
        }

        return $args;
    }

    public static function shouldDisplayErrors(?string $projectRoot = null): bool
    {
        $env = self::loadEnv($projectRoot ?? (string) getcwd());

        if (!self::parseBool($env['PINOOX_EXCEPTION'] ?? null, true)) {
            return false;
        }

        $mode = self::normalizeMode($env['APP_ENV'] ?? $env['MODE'] ?? 'production');

        if (in_array($mode, self::DEV_MODES, true)) {
            return self::parseBool($env['APP_DEBUG'] ?? null, true);
        }

        return self::parseBool($env['APP_DEBUG'] ?? null, false);
    }

    /**
     * Hide PHP warnings/notices; keep uncaught exceptions for CliErrorRenderer.
     *
     * @return list<string>
     */
    public static function quietPhpIniArgs(): array
    {
        return [
            '-d', 'display_errors=0',
            '-d', 'display_startup_errors=0',
            '-d', 'log_errors=0',
        ];
    }

    public static function quietPhpOutput(): void
    {
        ini_set('display_errors', '0');
        ini_set('display_startup_errors', '0');
        ini_set('log_errors', '0');

        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            if (in_array($severity, [E_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
                throw new \ErrorException($message, 0, $severity, $file, $line);
            }

            return true;
        }, self::errorReportingLevel());
    }

    private static function registerExceptionOnlyHandler(): void
    {
        error_reporting(self::errorReportingLevel());
    }

    private static function errorReportingLevel(): int
    {
        return E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED;
    }

    /**
     * @return array<string, string>
     */
    public static function loadEnv(string $projectRoot): array
    {
        $path = rtrim(str_replace('\\', '/', $projectRoot), '/') . '/.env';

        if (!is_file($path)) {
            return [];
        }

        $vars = [];

        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\"'");

            if ($key !== '') {
                $vars[$key] = $value;
            }
        }

        return $vars;
    }

    private static function normalizeMode(string $mode): string
    {
        $mode = strtolower(trim($mode));

        return match ($mode) {
            'dev', 'local' => 'development',
            'prod' => 'production',
            'testing' => 'test',
            default => $mode,
        };
    }

    private static function parseBool(?string $value, bool $default): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
