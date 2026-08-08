<?php

declare(strict_types=1);

use Pinoox\PinxCli\Command\MakeCommand;
use Pinoox\PinxCli\Command\MigrateCreateCommand;
use Symfony\Component\Console\Input\ArrayInput;

require __DIR__ . '/../vendor/autoload.php';

function assert_true(bool $condition, string $message): void
{
    if ($condition) {
        return;
    }

    fwrite(STDERR, $message . PHP_EOL);
    exit(1);
}

$create = new MigrateCreateCommand();
$make = new MakeCommand();

assert_true($create->getDefinition()->hasOption('create'), 'migrate:create should expose --create.');
assert_true($create->getDefinition()->hasOption('table'), 'migrate:create should expose --table.');
assert_true($make->getDefinition()->hasOption('create'), 'make should expose --create for migrations.');
assert_true($make->getDefinition()->hasOption('table'), 'make should expose --table for migrations.');

$forwardOptions = new ReflectionMethod($create, 'forwardOptions');
$forwardOptions->setAccessible(true);
$input = new ArrayInput([
    'name' => 'sync_legacy_flags',
    '--table' => 'users',
    '--create' => 'orders',
], $create->getDefinition());
$input->bind($create->getDefinition());

$forwarded = $forwardOptions->invoke($create, $input, ['create', 'table']);
assert_true(in_array('--create=orders', $forwarded, true), 'migrate:create should forward --create.');
assert_true(in_array('--table=users', $forwarded, true), 'migrate:create should forward --table.');

echo 'Migrate create command definition checks passed' . PHP_EOL;
