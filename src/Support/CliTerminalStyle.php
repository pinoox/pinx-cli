<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

final class CliTerminalStyle
{
    private const DEFAULT_WIDTH = 72;

    private readonly bool $decorated;

    public function __construct(?bool $decorated = null)
    {
        $this->decorated = $decorated ?? self::supportsColor();
    }

    public static function boot(): void
    {
        self::enableVt100();
    }

    public static function enableVt100(): void
    {
        if (PHP_SAPI !== 'cli' || DIRECTORY_SEPARATOR !== '\\' || !function_exists('sapi_windows_vt100_support')) {
            return;
        }

        foreach ([STDOUT, STDERR] as $stream) {
            if (is_resource($stream)) {
                @sapi_windows_vt100_support($stream, true);
            }
        }
    }

    public static function supportsColor(): bool
    {
        if (getenv('NO_COLOR') !== false) {
            return false;
        }

        if (getenv('FORCE_COLOR') !== false) {
            return true;
        }

        self::enableVt100();

        return function_exists('stream_isatty')
            && defined('STDERR')
            && @stream_isatty(STDERR);
    }

    public function banner(string $title, string $fg = '1;97', string $bg = '41'): string
    {
        $label = ' ' . $title . ' ';
        $width = max(mb_strlen($label) + 4, self::DEFAULT_WIDTH);
        $line = str_repeat(' ', max(0, (int) floor(($width - mb_strlen($label)) / 2))) . $label;
        $line = str_pad($line, $width, ' ');

        return $this->color($line, $fg, $bg);
    }

    public function rule(string $char = '-'): string
    {
        return $this->color(str_repeat($char, self::DEFAULT_WIDTH), '2;37');
    }

    public function section(string $title): string
    {
        return $this->color($title, '1;96');
    }

    public function field(string $label, string $value, string $valueColor = '97'): string
    {
        return '  '
            . $this->color(str_pad($label . ':', 10), '1;36')
            . ' '
            . $this->color($value, $valueColor);
    }

    public function bullet(string $title, string $summary = ''): string
    {
        $text = '  ' . $this->color('*', '1;91') . ' ' . $this->color($title, '1;93');

        return $summary !== '' ? $text . $this->color(' - ', '2;37') . $summary : $text;
    }

    public function wrap(string $text, int $indent = 2, string $color = '97'): string
    {
        $prefix = str_repeat(' ', $indent);
        $wrapped = wordwrap($text, self::DEFAULT_WIDTH - $indent, PHP_EOL . $prefix, true);

        return $prefix . $this->color($wrapped, $color);
    }

    public function shortClass(string $class): string
    {
        $short = $class;
        if (str_contains($class, '\\')) {
            $short = substr($class, strrpos($class, '\\') + 1);
        }

        return $this->color($short, '1;91');
    }

    public function dim(string $text): string
    {
        return $this->color($text, '2;37');
    }

    public function accent(string $text): string
    {
        return $this->color($text, '1;32');
    }

    public function color(string $text, string $style, ?string $background = null): string
    {
        if (!$this->decorated) {
            return $text;
        }

        $code = $background !== null ? $style . ';' . $background : $style;

        return "\033[" . $code . 'm' . $text . "\033[0m";
    }
}
