<?php

declare(strict_types=1);

use Pinoox\PinxCli\Support\CliErrorRenderer;
use Symfony\Component\Console\Exception\CommandNotFoundException;

require __DIR__ . '/../vendor/autoload.php';

function assert_contains(string $needle, string $haystack): void
{
    if (!str_contains($haystack, $needle)) {
        fwrite(STDERR, "Expected output to contain: {$needle}" . PHP_EOL . $haystack);
        exit(1);
    }
}

function assert_not_contains(string $needle, string $haystack): void
{
    if (str_contains($haystack, $needle)) {
        fwrite(STDERR, "Expected output not to contain: {$needle}" . PHP_EOL . $haystack);
        exit(1);
    }
}

$_SERVER['argv'] = ['pinx', 'serve'];

$renderer = new CliErrorRenderer();
$consoleOutput = $renderer->render(new CommandNotFoundException('Command "serve" is not defined.'));

assert_contains('Command error', $consoleOutput);
assert_contains('Command "serve" is not defined.', $consoleOutput);
assert_contains('pinx dev', $consoleOutput);
assert_contains('pinx list', $consoleOutput);
assert_not_contains('Pinoox Exception', $consoleOutput);
assert_not_contains('Application.php', $consoleOutput);

$runtimeOutput = $renderer->render(new RuntimeException('Could not detect app package.'));

assert_contains('Pinx Exception', $runtimeOutput);
assert_contains('Could not detect app package.', $runtimeOutput);
assert_contains('Run this command inside a Pinx single-app project', $runtimeOutput);
assert_not_contains('Pinoox Exception', $runtimeOutput);

echo 'CliErrorRenderer checks passed' . PHP_EOL;
