<?php

declare(strict_types=1);

use Pinoox\PinxCli\Command\DepsInstallCommand;
use Pinoox\PinxCli\Command\DepsStatusCommand;
use Pinoox\PinxCli\Command\DepsUpdateCommand;
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

$status = new DepsStatusCommand();
$install = new DepsInstallCommand();
$update = new DepsUpdateCommand();

assert_true($status->getDefinition()->hasOption('npm-only'), 'deps:status should expose npm filtering.');
assert_true(!$status->getDefinition()->hasOption('no-ci'), 'deps:status must not expose install-only options.');

foreach ([$install, $update] as $command) {
    assert_true($command->getDefinition()->hasOption('no-ci'), $command->getName() . ' should expose --no-ci.');
    assert_true($command->getDefinition()->hasOption('plain'), $command->getName() . ' should expose --plain.');
    assert_true(
        $command->getDefinition()->hasOption('continue-on-error'),
        $command->getName() . ' should expose --continue-on-error.',
    );

    $forwardedOptions = (new ReflectionMethod($command, 'forwardedDepsOptionNames'))->invoke($command);
    assert_true(in_array('no-ci', $forwardedOptions, true), $command->getName() . ' should forward --no-ci.');
    assert_true(in_array('plain', $forwardedOptions, true), $command->getName() . ' should forward --plain.');
}

$statusOptions = (new ReflectionMethod($status, 'forwardedDepsOptionNames'))->invoke($status);
assert_true(!in_array('no-ci', $statusOptions, true), 'deps:status must not forward --no-ci.');

$aliases = CommandCatalog::aliases();
assert_same(['deps:st'], $aliases['deps:status'] ?? null, 'deps:status alias mismatch.');
assert_same(['deps:i'], $aliases['deps:install'] ?? null, 'deps:install alias mismatch.');
assert_same(['deps:up'], $aliases['deps:update'] ?? null, 'deps:update alias mismatch.');

echo 'Deps command definition checks passed' . PHP_EOL;
