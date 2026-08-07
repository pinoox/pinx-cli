<?php

declare(strict_types=1);

use Pinoox\PinxCli\Support\SingleAppRepairer;

require __DIR__ . '/../vendor/autoload.php';

function assert_sync_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function remove_sync_directory(string $directory): void
{
    if (!is_dir($directory)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );

    foreach ($iterator as $item) {
        $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }

    @rmdir($directory);
}

$root = sys_get_temp_dir() . '/pinx-sync-composer-' . uniqid('', true);
mkdir($root);

$app = <<<'PHP'
<?php

return [
    'package' => 'com_test_existing',
    'name' => 'Existing App',
    'description' => 'Existing app description',
    'version-name' => '2.3.4',
];
PHP;

file_put_contents($root . '/app.php', $app);

$repairer = new SingleAppRepairer();
$changed = $repairer->sync($root);

assert_sync_true(in_array('composer.json', $changed, true), 'sync should report a created composer.json');
assert_sync_true(is_file($root . '/composer.json'), 'sync should create a missing composer.json');

$composer = json_decode((string) file_get_contents($root . '/composer.json'), true);
assert_sync_true(is_array($composer), 'generated composer.json should contain valid JSON');
assert_sync_true(
    isset($composer['require']['pinoox/pincore']),
    'generated composer.json should require pinoox/pincore',
);
assert_sync_true(
    ($composer['name'] ?? null) === 'test/existing',
    'generated composer.json should derive its name from the app package',
);
assert_sync_true(
    ($composer['version'] ?? null) === '2.3.4',
    'generated composer.json should use the app version',
);
assert_sync_true(
    ($composer['description'] ?? null) === 'Existing app description',
    'generated composer.json should use the app description',
);
assert_sync_true(
    ($composer['type'] ?? null) === 'project',
    'generated composer.json should remain a Composer project',
);
assert_sync_true(
    file_get_contents($root . '/app.php') === $app,
    'sync should not change app.php',
);

$customComposer = <<<'JSON'
{
  "name": "acme/custom-app",
  "require": {
    "php": "^8.3",
    "acme/private-package": "^2.0"
  }
}
JSON;

file_put_contents($root . '/composer.json', $customComposer);
$changed = $repairer->sync($root, overwrite: true);
$mergedComposerContents = file_get_contents($root . '/composer.json');
$mergedComposer = json_decode((string) $mergedComposerContents, true);

assert_sync_true(
    in_array('composer.json', $changed, true),
    'sync should report an existing composer.json when dependencies are added',
);
assert_sync_true(
    is_array($mergedComposer),
    'merged composer.json should contain valid JSON',
);
assert_sync_true(
    ($mergedComposer['require']['php'] ?? null) === '^8.3',
    'sync should preserve an existing dependency constraint',
);
assert_sync_true(
    ($mergedComposer['require']['acme/private-package'] ?? null) === '^2.0',
    'sync should preserve custom dependencies',
);
assert_sync_true(
    ($mergedComposer['name'] ?? null) === 'acme/custom-app',
    'sync should preserve metadata in an existing composer.json',
);
assert_sync_true(
    isset($mergedComposer['require']['pinoox/pincore']),
    'sync should add a missing pinoox/pincore dependency',
);
assert_sync_true(
    isset($mergedComposer['require-dev']['pinoox/pinx-cli']),
    'sync should add missing single-app development dependencies',
);

$changed = $repairer->sync($root, overwrite: true);

assert_sync_true(
    !in_array('composer.json', $changed, true),
    'repeated sync should not change an already complete composer.json',
);
assert_sync_true(
    file_get_contents($root . '/composer.json') === $mergedComposerContents,
    'repeated sync should preserve composer.json byte-for-byte',
);

remove_sync_directory($root);

echo 'Sync composer manifest checks passed' . PHP_EOL;
