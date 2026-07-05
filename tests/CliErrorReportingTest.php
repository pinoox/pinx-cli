<?php

declare(strict_types=1);

use Pinoox\PinxCli\Support\CliErrorReporting;

require __DIR__ . '/../vendor/autoload.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function assert_false(bool $condition, string $message): void
{
    assert_true(!$condition, $message);
}

function assert_same(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, 'Expected ' . var_export($expected, true) . ' got ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$root = sys_get_temp_dir() . '/pinx-cli-error-reporting-' . uniqid('', true);
mkdir($root);

file_put_contents($root . '/.env', "APP_ENV=development\nDB_CONNECTION=devdb\n");

assert_true(CliErrorReporting::shouldDisplayErrors($root), 'development should display errors by default');
assert_same(
    ['-d', 'display_errors=0', '-d', 'display_startup_errors=0', '-d', 'log_errors=0'],
    array_slice(CliErrorReporting::quietPhpIniArgs(), 0, 6),
);
assert_contains('-d', CliErrorReporting::phpIniArgs($root));
assert_contains('error_reporting=', implode(' ', CliErrorReporting::phpIniArgs($root)));

file_put_contents($root . '/.env', "APP_ENV=production\nAPP_DEBUG=false\nPINOOX_EXCEPTION=false\n");

assert_false(CliErrorReporting::shouldDisplayErrors($root), 'production with flags off should hide exceptions');
assert_same(CliErrorReporting::quietPhpIniArgs(), CliErrorReporting::phpIniArgs($root));

file_put_contents($root . '/.env', "APP_ENV=production\nAPP_DEBUG=true\nPINOOX_EXCEPTION=true\n");

assert_true(CliErrorReporting::shouldDisplayErrors($root), 'production with APP_DEBUG should display exceptions');

@unlink($root . '/.env');
@rmdir($root);

echo 'CliErrorReporting checks passed' . PHP_EOL;

function assert_contains(string $needle, array|string $haystack): void
{
    $text = is_array($haystack) ? implode(' ', $haystack) : $haystack;

    if (!str_contains($text, $needle)) {
        fwrite(STDERR, "Expected output to contain: {$needle}" . PHP_EOL . $text);
        exit(1);
    }
}
