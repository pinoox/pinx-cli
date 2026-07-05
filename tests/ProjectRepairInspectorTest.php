<?php

declare(strict_types=1);

use Pinoox\PinxCli\Support\ProjectRepairInspector;
use Pinoox\PinxCli\Support\ProjectScaffolder;

require __DIR__ . '/../vendor/autoload.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

function assert_same(mixed $expected, mixed $actual, string $message = ''): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, ($message !== '' ? $message . ': ' : '')
            . 'Expected ' . var_export($expected, true)
            . ' got ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$root = sys_get_temp_dir() . '/pinx-repair-' . uniqid('', true);
mkdir($root);
mkdir($root . '/routes');

file_put_contents($root . '/app.php', <<<'PHP'
<?php

return [
    'package' => 'com_test_repair',
    'router' => ['routes' => ['routes/web.php']],
];
PHP);

file_put_contents($root . '/composer.json', '{"require":{"pinoox/pincore":"^3.1"}}');
file_put_contents($root . '/routes/actions.php', <<<'PHP'
<?php

use App\com_test_repair\Router\Actions;
use function Pinoox\Router\action;

action(Actions::HOME, ['home']);
PHP);

$supportFiles = ProjectScaffolder::supportSyncFiles();
assert_true(!in_array('app.php', $supportFiles, true), 'sync must not include app.php');
assert_true(!in_array('routes/web.php', $supportFiles, true), 'sync must not include routes/web.php');
assert_true(!in_array('composer.json', $supportFiles, true), 'sync must not include composer.json');

$inspector = new ProjectRepairInspector();
$findings = $inspector->diagnose($root, 'com_test_repair');
$ids = array_map(static fn ($finding) => $finding->id, $findings);

assert_true(in_array('router:actions-class', $ids, true), 'missing Router/Actions.php should be detected');

$changed = $inspector->fix(
    $findings,
    $root,
    'com_test_repair',
    ProjectScaffolder::defaultReplacements('com_test_repair'),
    overwriteSupportFiles: false,
);

assert_true(in_array('Router/Actions.php', $changed, true), 'repair should generate Router/Actions.php');
assert_true(is_file($root . '/Router/Actions.php'), 'Router/Actions.php should exist after repair');

$contents = file_get_contents($root . '/Router/Actions.php');
assert_true(is_string($contents), 'generated Router/Actions.php should be readable');
assert_true(str_contains($contents, "public const HOME = 'home';"), 'HOME constant should be generated');

$remaining = $inspector->diagnose($root, 'com_test_repair');
$remainingIds = array_map(static fn ($finding) => $finding->id, $remaining);
assert_true(!in_array('router:actions-class', $remainingIds, true), 'Router/Actions issue should be resolved');

@unlink($root . '/routes/actions.php');
@unlink($root . '/routes/web.php');
@unlink($root . '/app.php');
@unlink($root . '/composer.json');
@unlink($root . '/Router/Actions.php');
@rmdir($root . '/routes');
@rmdir($root . '/Router');
@rmdir($root);

echo 'ProjectRepairInspector checks passed' . PHP_EOL;
