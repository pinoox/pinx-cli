<?php

declare(strict_types=1);

use Pinoox\PinxCli\Command\PinrollLogsCommand;
use Pinoox\PinxCli\Support\CommandCatalog;

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

$command = new PinrollLogsCommand();
$definition = $command->getDefinition();

assert_same('pinroll:logs', $command->getName(), 'Command name mismatch.');
assert_true(in_array('logs', $command->getAliases(), true), 'pinroll:logs must have logs alias.');
assert_true(in_array('pinroll:log', $command->getAliases(), true), 'pinroll:logs must have pinroll:log alias.');
assert_true(in_array('log', $command->getAliases(), true), 'pinroll:logs must have log alias.');

assert_true($definition->hasArgument('host'), 'pinroll:logs should accept host argument.');
assert_true($definition->hasOption('file'), 'pinroll:logs should expose --file option.');
assert_true($definition->hasOption('latest'), 'pinroll:logs should expose --latest option.');
assert_true($definition->hasOption('lines'), 'pinroll:logs should expose --lines option.');
assert_true($definition->hasOption('type'), 'pinroll:logs should expose --type option.');
assert_true($definition->hasOption('search'), 'pinroll:logs should expose --search option.');
assert_true($definition->hasOption('level'), 'pinroll:logs should expose --level option.');
assert_true($definition->hasOption('local'), 'pinroll:logs should expose --local option.');
assert_true($definition->hasOption('json'), 'pinroll:logs should expose --json option.');
assert_true($definition->hasOption('raw'), 'pinroll:logs should expose --raw option.');

$aliases = CommandCatalog::aliases();
assert_same(['logs', 'pinroll:log', 'log'], $aliases['pinroll:logs'] ?? null, 'CommandCatalog aliases mismatch.');

echo 'PinrollLogsCommand definition checks passed' . PHP_EOL;
