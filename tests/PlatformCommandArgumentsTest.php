<?php

declare(strict_types=1);

use Pinoox\PinxCli\Support\PlatformCommandArguments;

require __DIR__ . '/../vendor/autoload.php';

function assert_same(mixed $expected, mixed $actual, string $message): void
{
    if ($expected === $actual) {
        return;
    }

    fwrite(
        STDERR,
        $message . PHP_EOL
        . 'Expected: ' . var_export($expected, true) . PHP_EOL
        . 'Actual:   ' . var_export($actual, true) . PHP_EOL,
    );
    exit(1);
}

$cases = [
    [['deps:status'], ['deps', 'status']],
    [['deps:st', 'all'], ['deps', 'status', 'all']],
    [['deps:install', 'platform'], ['deps', 'install', 'platform']],
    [['deps:i', 'com_shop', '--plain'], ['deps', 'install', 'com_shop', '--plain']],
    [['deps:update', 'all', '--npm-only'], ['deps', 'update', 'all', '--npm-only']],
    [['deps:up', '--no-ansi', 'com_shop'], ['deps', 'update', '--no-ansi', 'com_shop']],
    [['--no-interaction', 'deps:i', 'all'], ['--no-interaction', 'deps', 'install', 'all']],
    [['deps', 'install', 'all'], ['deps', 'install', 'all']],
    [['fe:install', 'com_shop'], ['fe:install', 'com_shop']],
    [['custom', 'deps:i'], ['custom', 'deps:i']],
];

foreach ($cases as [$input, $expected]) {
    assert_same($expected, PlatformCommandArguments::normalize($input), 'Platform command normalization failed.');
}

echo 'PlatformCommandArguments checks passed' . PHP_EOL;
