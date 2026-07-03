<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

use Symfony\Component\Console\Exception\CommandNotFoundException;
use Symfony\Component\Console\Exception\ExceptionInterface as ConsoleExceptionInterface;

final class CliErrorRenderer
{
    public function render(\Throwable $exception): string
    {
        if ($exception instanceof ConsoleExceptionInterface) {
            return $this->renderConsoleError($exception);
        }

        return $this->renderPinooxError($exception);
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
            $this->color('Command error', '1;97', '43'),
            $this->wrap($message, 2, '97'),
            '',
        ];

        if ($command !== null && $command !== '') {
            $lines[] = $this->field('Command', $command);
        }

        if ($suggestions !== []) {
            $lines[] = '';
            $lines[] = $this->section('Try');

            foreach ($suggestions as $suggestion) {
                $lines[] = '  - ' . $suggestion;
            }
        }

        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }

    private function renderPinooxError(\Throwable $exception): string
    {
        $message = $exception->getMessage() !== '' ? $exception->getMessage() : 'No exception message.';
        $project = $this->relativePath((string) getcwd());
        $location = $this->relativePath($exception->getFile()) . ':' . $exception->getLine();

        $lines = [
            '',
            $this->color('Pinx failed', '1;97', '41'),
            $this->wrap($message, 2, '97'),
            '',
            $this->field('Type', get_class($exception)),
            $this->field('Location', $location),
            $this->field('Project', $project),
        ];

        $hint = $this->hintFor($exception);

        if ($hint !== null) {
            $lines[] = '';
            $lines[] = $this->section('Hint');
            $lines[] = '  ' . $hint;
        }

        $trace = $this->userTrace($exception);

        if ($trace !== []) {
            $lines[] = '';
            $lines[] = $this->section('Trace');

            foreach ($trace as $index => $frame) {
                $lines[] = sprintf('  #%02d %s', $index, $frame);
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
                $suggestions[] = $this->color($script . ' ' . $alternative, '1;32');
            }

            if ($command === 'serve') {
                $suggestions[] = $this->color($script . ' dev', '1;32') . ' to start the single-app development server.';
            }
        }

        if ($command !== null && $command !== '') {
            $suggestions[] = $this->color($script . ' help', '1;32') . ' to see available commands.';
        }

        $suggestions[] = $this->color($script . ' list', '1;32') . ' to list all commands.';

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

    private function field(string $label, string $value): string
    {
        return sprintf('  %s %s', str_pad($label . ':', 10), $value);
    }

    private function section(string $title): string
    {
        return $this->color($title, '1;96');
    }

    private function wrap(string $text, int $indent = 0, ?string $color = null): string
    {
        $prefix = str_repeat(' ', $indent);
        $wrapped = wordwrap($text, 88 - $indent, PHP_EOL . $prefix, true);
        $wrapped = $prefix . $wrapped;

        return $color !== null ? $this->color($wrapped, $color) : $wrapped;
    }

    private function color(string $text, string $style, ?string $background = null): string
    {
        if (!$this->supportsColor()) {
            return $text;
        }

        $code = $background !== null ? $style . ';' . $background : $style;

        return "\033[" . $code . 'm' . $text . "\033[0m";
    }

    private function supportsColor(): bool
    {
        return !isset($_SERVER['NO_COLOR'])
            && function_exists('stream_isatty')
            && defined('STDERR')
            && stream_isatty(STDERR);
    }
}
