<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\ExceptionInterface as ConsoleExceptionInterface;

final class CliErrorRenderer
{
    private readonly CliTerminalStyle $style;

    public function __construct(?CliTerminalStyle $style = null)
    {
        $this->style = $style ?? new CliTerminalStyle();
    }

    public function render(\Throwable $exception): string
    {
        if ($exception instanceof ConsoleExceptionInterface) {
            return $this->renderConsoleError($exception);
        }

        return $this->renderRuntimeError($exception);
    }

    private function renderConsoleError(ConsoleExceptionInterface $exception): string
    {
        $message = $exception->getMessage() !== '' ? $exception->getMessage() : 'Invalid command usage.';
        $argv = array_values(array_map('strval', $_SERVER['argv'] ?? []));
        $script = $this->scriptName($argv);
        $command = $argv[1] ?? null;
        $suggestions = $this->consoleSuggestions($exception, $script, $command);

        $lines = [
            '',
            $this->style->banner('Command error', '1;97', '43'),
            $this->style->rule(),
            $this->style->wrap($message),
            '',
        ];

        if ($command !== null && $command !== '') {
            $lines[] = $this->style->field('Command', $command);
        }

        if ($suggestions !== []) {
            $lines[] = '';
            $lines[] = $this->style->section('Try');
            $lines[] = '';

            foreach ($suggestions as $suggestion) {
                $lines[] = '  ' . $this->style->color('>', '1;96') . ' ' . $suggestion;
            }
        }

        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }

    private function renderRuntimeError(\Throwable $exception): string
    {
        $class = get_class($exception);
        $message = $exception->getMessage() !== '' ? $exception->getMessage() : 'No exception message.';
        $project = $this->relativePath((string) getcwd());
        $location = $this->relativePath($exception->getFile()) . ':' . $exception->getLine();

        $lines = [
            '',
            $this->style->banner('Pinx Exception', '1;97', '41'),
            $this->style->rule(),
            $this->style->shortClass($class),
            $this->style->dim('  ' . $class),
            '',
            $this->style->wrap($message),
            '',
            $this->style->rule('-'),
            $this->style->field('Location', $location, '1;97'),
            $this->style->field('Project', $project, '2;37'),
        ];

        $hint = $this->hintFor($exception);

        if ($hint !== null) {
            $lines[] = '';
            $lines[] = $this->style->section('Hint');
            $lines[] = '';
            $lines[] = $this->style->bullet('Suggestion', $hint);
        }

        $trace = $this->userTrace($exception);

        if ($trace !== []) {
            $lines[] = '';
            $lines[] = $this->style->section('Trace');
            $lines[] = '';

            foreach ($trace as $index => $frame) {
                $lines[] = sprintf(
                    '  %s %s',
                    $this->style->color(sprintf('#%02d', $index), '1;90'),
                    $this->style->color($frame, '2;37'),
                );
            }
        }

        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }

    private function consoleSuggestions(ConsoleExceptionInterface $exception, string $script, ?string $command): array
    {
        $suggestions = [];

        if ($exception instanceof CommandNotFoundException) {
            foreach ($exception->getAlternatives() as $alternative) {
                $suggestions[] = $this->style->accent($script . ' ' . $alternative);
            }

            if ($command === 'serve') {
                $suggestions[] = $this->style->accent($script . ' dev') . $this->style->dim(' - start the development server');
            }
        }

        if ($command !== null && $command !== '') {
            $suggestions[] = $this->style->accent($script . ' help') . $this->style->dim(' - command usage');
        }

        $suggestions[] = $this->style->accent($script . ' list') . $this->style->dim(' - all commands');

        return array_values(array_unique($suggestions));
    }

    private function hintFor(\Throwable $exception): ?string
    {
        $message = $exception->getMessage();

        if (str_contains($message, 'pinoox/pincore is not installed') || str_contains($message, 'composer install')) {
            return 'Run composer install in the project, then try the command again.';
        }

        if (str_contains($message, 'Could not detect app package')) {
            return 'Run this command inside a Pinx single-app project, or set PINX_PACKAGE in .env.';
        }

        if (str_contains($message, 'Pinx Inspector is not installed')) {
            return 'Install Inspector with composer require --dev pinoox/pinx-inspector.';
        }

        return 'Run pinx doctor to check the project setup.';
    }

    private function userTrace(\Throwable $exception): array
    {
        $frames = [];
        $base = str_replace('\\', '/', dirname(__DIR__, 2));

        foreach ($exception->getTrace() as $frame) {
            $file = isset($frame['file']) ? str_replace('\\', '/', (string) $frame['file']) : '';

            if ($file === '' || str_contains($file, '/vendor/')) {
                continue;
            }

            $frames[] = $this->relativePath($file) . ':' . (string) ($frame['line'] ?? '?');

            if (count($frames) >= 6) {
                break;
            }
        }

        if ($frames === [] && str_starts_with(str_replace('\\', '/', $exception->getFile()), $base)) {
            $frames[] = $this->relativePath($exception->getFile()) . ':' . $exception->getLine();
        }

        return $frames;
    }

    private function scriptName(array $argv): string
    {
        $script = str_replace('\\', '/', (string) ($argv[0] ?? 'pinx'));

        if (str_ends_with($script, '/pinx') || $script === 'pinx') {
            return 'pinx';
        }

        return basename($script) ?: 'pinx';
    }

    private function relativePath(string $path): string
    {
        $normalized = str_replace('\\', '/', $path);
        $cwd = str_replace('\\', '/', (string) getcwd());

        if ($cwd !== '' && str_starts_with($normalized, $cwd . '/')) {
            return substr($normalized, strlen($cwd) + 1);
        }

        return $path;
    }
}
