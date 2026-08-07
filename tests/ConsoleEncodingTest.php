<?php

declare(strict_types=1);

use Pinoox\PinxCli\Support\ConsoleEncoding;

require __DIR__ . '/../vendor/autoload.php';

function assert_true(bool $condition, string $message): void
{
    if ($condition) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

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

ConsoleEncoding::bootUtf8();
ConsoleEncoding::ensureUtf8();

if (PHP_OS_FAMILY === 'Windows') {
    putenv('PINOOX_CLI_UNICODE');
    unset($_SERVER['PINOOX_CLI_UNICODE']);
    putenv('PINOOX_CLI_ASCII');
    unset($_SERVER['PINOOX_CLI_ASCII']);
    putenv('WT_SESSION');
    unset($_SERVER['WT_SESSION']);

    assert_true(
        ConsoleEncoding::prefersAsciiUi(),
        'Windows without Windows Terminal should prefer ASCII UI by default.',
    );
} else {
    assert_true(ConsoleEncoding::isUtf8Console(), 'Non-Windows consoles should report UTF-8 support.');
    assert_true(!ConsoleEncoding::prefersAsciiUi(), 'Non-Windows consoles should keep Unicode UI.');
}

putenv('PINOOX_CLI_ASCII=1');
$_SERVER['PINOOX_CLI_ASCII'] = '1';
assert_true(ConsoleEncoding::prefersAsciiUi(), 'PINOOX_CLI_ASCII should force ASCII UI.');
assert_same(
    ['deps', 'install', '--plain'],
    ConsoleEncoding::withAsciiUiArgs(['deps', 'install']),
    'ASCII mode should inject --plain for deps commands.',
);
assert_same(
    ['PINOOX_CLI_ASCII' => '1'],
    ConsoleEncoding::processEnv(),
    'ASCII mode should export PINOOX_CLI_ASCII for child processes.',
);

putenv('PINOOX_CLI_ASCII');
unset($_SERVER['PINOOX_CLI_ASCII']);
putenv('PINOOX_CLI_UNICODE=1');
$_SERVER['PINOOX_CLI_UNICODE'] = '1';
assert_true(!ConsoleEncoding::prefersAsciiUi(), 'PINOOX_CLI_UNICODE=1 should keep Unicode UI.');
assert_same(
    ['deps', 'install'],
    ConsoleEncoding::withAsciiUiArgs(['deps', 'install']),
    'Unicode mode should not inject --plain.',
);

putenv('PINOOX_CLI_UNICODE');
unset($_SERVER['PINOOX_CLI_UNICODE']);

echo 'ConsoleEncoding checks passed' . PHP_EOL;
