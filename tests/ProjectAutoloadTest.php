<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pinoox\PinxCli\Support\ProjectAutoload;

$root = sys_get_temp_dir() . '/pinx-cli-project-autoload-' . bin2hex(random_bytes(4));
$vendorDir = $root . '/vendor';
$functionsDir = $vendorDir . '/pinoox/pincore/functions';

mkdir($functionsDir, 0777, true);

file_put_contents($functionsDir . '/base.php', <<<'PHP'
<?php
if (!function_exists('theme_flow_aliases')) {
    function theme_flow_aliases(array $contexts): array
    {
        return ['theme' => array_fill_keys($contexts, true)];
    }
}
PHP);

file_put_contents($vendorDir . '/autoload.php', <<<'PHP'
<?php
require_once __DIR__ . '/pinoox/pincore/functions/base.php';
PHP);

assert_true(!function_exists('theme_flow_aliases') || !isset($GLOBALS['__pinx_autoload_test']), 'precondition');

ProjectAutoload::boot($root);

assert_true(function_exists('theme_flow_aliases'), 'project helpers must load via vendor/autoload.php');
assert_true(theme_flow_aliases(['site'])['theme']['site'] === true, 'theme_flow_aliases must be callable');

// Second boot is a no-op
ProjectAutoload::boot($root);

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

echo "ProjectAutoloadTest: ok\n";

// cleanup
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::CHILD_FIRST,
);
foreach ($iterator as $file) {
    $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
}
rmdir($root);
