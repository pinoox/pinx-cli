<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

use Symfony\Component\Process\Process;

final class PlatformCliForwarder
{
    /**
     * @param list<string> $argvArgs
     */
    public static function forward(PlatformContext $platform, array $argvArgs): int
    {
        $requestedArgs = $argvArgs;
        $argvArgs = PlatformCommandArguments::normalize($argvArgs);
        $argvArgs = self::ensureAnsiArgs(ConsoleEncoding::withAsciiUiArgs($argvArgs));

        $command = array_merge(
            ['php'],
            CliErrorReporting::phpIniArgs($platform->root),
            [$platform->pinooxScript()],
            $argvArgs,
        );

        self::noteForward($platform, $requestedArgs, $argvArgs);

        $env = self::buildEnv($platform);
        $useTty = self::shouldUseTty($argvArgs);

        ConsoleEncoding::ensureUtf8();

        $process = new Process($command, $platform->root, $env, null, null);
        $process->setTty($useTty);

        if ($useTty) {
            $exitCode = $process->run();
            ConsoleEncoding::ensureUtf8();

            return $exitCode;
        }

        $exitCode = $process->run(static function (string $type, string $buffer): void {
            ConsoleEncoding::ensureUtf8();
            $stream = $type === Process::ERR ? STDERR : STDOUT;

            if (is_resource($stream)) {
                fwrite($stream, $buffer);

                return;
            }

            echo $buffer;
        });

        ConsoleEncoding::ensureUtf8();

        return $exitCode;
    }

    /**
     * @param list<string> $argvArgs
     * @return list<string>
     */
    private static function ensureAnsiArgs(array $argvArgs): array
    {
        if (self::hasFlag($argvArgs, '--no-ansi') || self::hasQuietFlag($argvArgs)) {
            return $argvArgs;
        }

        if (!CliTerminalStyle::supportsColor()) {
            return $argvArgs;
        }

        if (self::hasFlag($argvArgs, '--ansi')) {
            return $argvArgs;
        }

        return array_merge(['--ansi'], $argvArgs);
    }

    /**
     * @return array<string, string>
     */
    private static function buildEnv(PlatformContext $platform): array
    {
        $env = array_merge($_ENV, [
            'PINOOX_BASE_PATH' => $platform->root,
            'PINOOX_CORE_PATH' => CorePath::resolve($platform->root),
            'PINX_FORWARDED' => '1',
            'PINOOX_CLI_INVOKE' => 'pinx',
        ], DevApp::pincoreEnv($platform->root), ConsoleEncoding::processEnv());

        if (CliTerminalStyle::supportsColor()) {
            $env['FORCE_COLOR'] = '1';
            $env['CLICOLOR_FORCE'] = '1';
        }

        foreach (['COLORTERM', 'TERM', 'COLUMNS', 'LINES', 'ANSICON'] as $key) {
            $value = getenv($key);

            if (is_string($value) && $value !== '') {
                $env[$key] = $value;
            }
        }

        if (CliErrorReporting::shouldDisplayErrors($platform->root)) {
            $env['FORCE_COLOR'] = '1';
        }

        return $env;
    }

    /**
     * @param list<string> $argvArgs
     */
    private static function shouldUseTty(array $argvArgs): bool
    {
        if (!Process::isTtySupported()) {
            return false;
        }

        if (self::hasFlag($argvArgs, '--no-interaction') || self::hasFlag($argvArgs, '-n')) {
            return false;
        }

        return is_resource(STDOUT) && @stream_isatty(STDOUT);
    }

    /**
     * @param list<string> $requestedArgs
     * @param list<string> $forwardedArgs
     */
    private static function noteForward(PlatformContext $platform, array $requestedArgs, array $forwardedArgs): void
    {
        if (getenv('PINX_FORWARDED') === '1') {
            return;
        }

        if (self::hasQuietFlag($forwardedArgs)) {
            return;
        }

        if (!is_resource(STDERR) || !@stream_isatty(STDERR)) {
            return;
        }

        $style = new CliTerminalStyle();
        $forwardedArgs = array_values(array_filter(
            $forwardedArgs,
            static fn (string $arg): bool => $arg !== '--ansi',
        ));
        $requested = 'pinx ' . self::formatArgs($requestedArgs);
        $forwarded = $platform->invokeLabel() . ' ' . self::formatArgs($forwardedArgs);

        fwrite(
            STDERR,
            PHP_EOL
            . $style->section('  Pinoox platform')
            . PHP_EOL
            . $style->field('Command', $requested)
            . PHP_EOL
            . $style->field('Running', $forwarded, '1;32')
            . PHP_EOL
            . PHP_EOL,
        );
    }

    /**
     * @param list<string> $args
     */
    private static function formatArgs(array $args): string
    {
        return implode(' ', array_map(
            static fn (string $arg): string => str_contains($arg, ' ') ? '"' . $arg . '"' : $arg,
            $args,
        ));
    }

    /**
     * @param list<string> $argvArgs
     */
    private static function hasQuietFlag(array $argvArgs): bool
    {
        return self::hasFlag($argvArgs, '--quiet')
            || self::hasFlag($argvArgs, '-q');
    }

    /**
     * @param list<string> $argvArgs
     */
    private static function hasFlag(array $argvArgs, string $flag): bool
    {
        return in_array($flag, $argvArgs, true);
    }
}
