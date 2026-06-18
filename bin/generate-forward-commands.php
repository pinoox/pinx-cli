<?php

declare(strict_types=1);

/**
 * One-off generator for pincore forward command wrappers.
 * Run: php bin/generate-forward-commands.php
 */

$commands = [
    // Database
    ['DbListCommand', 'db:list', 'List database connections for the current app or platform', ['databases'], [], ['target'], ['all' => 'VALUE_NONE', 'test' => 'VALUE_NONE', 'json' => 'VALUE_NONE']],
    ['DbShowCommand', 'db:show', 'Show database connection details', ['database:show'], [], ['target'], ['json' => 'VALUE_NONE']],
    ['DbTestCommand', 'db:test', 'Test database connectivity', ['database:test'], [], ['target'], ['driver' => 'VALUE_REQUIRED', 'host' => 'VALUE_REQUIRED', 'database' => 'VALUE_REQUIRED', 'username' => 'VALUE_REQUIRED', 'password' => 'VALUE_REQUIRED', 'port' => 'VALUE_REQUIRED']],
    ['DbCreateCommand', 'db:create', 'Configure platform or app database settings', ['database:create', 'make:db'], [], ['target'], ['name' => 'VALUE_REQUIRED', 'default' => 'VALUE_NONE', 'driver' => 'VALUE_REQUIRED', 'use' => 'VALUE_REQUIRED', 'host' => 'VALUE_REQUIRED', 'database' => 'VALUE_REQUIRED', 'username' => 'VALUE_REQUIRED', 'password' => 'VALUE_REQUIRED', 'prefix' => 'VALUE_REQUIRED', 'port' => 'VALUE_REQUIRED', 'set' => 'VALUE_IS_ARRAY']],
    ['DbUpdateCommand', 'db:update', 'Update database connection settings', ['database:update'], [], ['target'], ['driver' => 'VALUE_REQUIRED', 'use' => 'VALUE_REQUIRED', 'host' => 'VALUE_REQUIRED', 'database' => 'VALUE_REQUIRED', 'username' => 'VALUE_REQUIRED', 'password' => 'VALUE_REQUIRED', 'prefix' => 'VALUE_REQUIRED', 'port' => 'VALUE_REQUIRED', 'reset' => 'VALUE_NONE', 'set' => 'VALUE_IS_ARRAY']],
    ['DbPrefixCommand', 'db:prefix', 'Change the app table prefix', ['database:prefix'], [], ['package', 'prefix'], ['use' => 'VALUE_REQUIRED']],
    // Roles
    ['RoleListCommand', 'role:list', 'List roles for the current app', ['roles'], [], [], ['json' => 'VALUE_NONE']],
    ['RoleCreateCommand', 'role:create', 'Create a role', ['make:role'], [], [], ['key' => 'VALUE_REQUIRED', 'name' => 'VALUE_REQUIRED', 'description' => 'VALUE_REQUIRED']],
    ['RoleShowCommand', 'role:show', 'Show a role', [], [], ['role'], ['permissions' => 'VALUE_NONE', 'json' => 'VALUE_NONE']],
    ['RoleUpdateCommand', 'role:update', 'Update a role', [], [], ['role'], ['key' => 'VALUE_REQUIRED', 'name' => 'VALUE_REQUIRED', 'description' => 'VALUE_REQUIRED']],
    ['RoleDeleteCommand', 'role:delete', 'Delete a role', [], [], ['role'], ['force' => 'VALUE_NONE']],
    ['RolePermissionCommand', 'role:permission', 'Attach or detach permissions on a role', ['role:permissions'], [], ['role'], ['attach' => 'VALUE_IS_ARRAY', 'detach' => 'VALUE_IS_ARRAY', 'sync' => 'VALUE_NONE', 'force' => 'VALUE_NONE']],
    // Permissions
    ['PermissionListCommand', 'permission:list', 'List permissions', ['permissions'], [], [], ['json' => 'VALUE_NONE']],
    ['PermissionCreateCommand', 'permission:create', 'Create a permission', ['make:permission'], [], [], ['key' => 'VALUE_REQUIRED', 'name' => 'VALUE_REQUIRED', 'description' => 'VALUE_REQUIRED']],
    ['PermissionShowCommand', 'permission:show', 'Show a permission', [], [], ['permission'], ['roles' => 'VALUE_NONE', 'json' => 'VALUE_NONE']],
    ['PermissionDeleteCommand', 'permission:delete', 'Delete a permission', [], [], ['permission'], ['force' => 'VALUE_NONE']],
    // Tokens
    ['TokenListCommand', 'token:list', 'List session tokens', ['tokens'], [], [], ['json' => 'VALUE_NONE']],
    ['TokenShowCommand', 'token:show', 'Show a token', [], [], ['token'], ['reveal' => 'VALUE_NONE', 'json' => 'VALUE_NONE']],
    ['TokenCreateCommand', 'token:create', 'Create a session token', ['make:token'], [], [], ['user' => 'VALUE_REQUIRED', 'name' => 'VALUE_REQUIRED', 'data' => 'VALUE_REQUIRED', 'json' => 'VALUE_REQUIRED', 'lifetime' => 'VALUE_REQUIRED', 'unit' => 'VALUE_REQUIRED', 'key' => 'VALUE_REQUIRED']],
    ['TokenUpdateCommand', 'token:update', 'Update a token', [], [], ['token'], ['name' => 'VALUE_REQUIRED', 'data' => 'VALUE_REQUIRED', 'json' => 'VALUE_REQUIRED', 'lifetime' => 'VALUE_REQUIRED', 'unit' => 'VALUE_REQUIRED']],
    ['TokenDeleteCommand', 'token:delete', 'Delete a token', ['token:remove'], [], ['token'], ['force' => 'VALUE_NONE']],
    ['TokenRevokeUserCommand', 'token:revoke-user', 'Revoke all tokens for a user', ['token:revoke'], [], ['user'], ['force' => 'VALUE_NONE']],
    ['TokenPurgeCommand', 'token:purge', 'Delete expired tokens', ['token:cleanup'], [], [], ['force' => 'VALUE_NONE', 'json' => 'VALUE_NONE']],
    // Files
    ['FileListCommand', 'file:list', 'List uploaded files', ['files'], [], [], ['group' => 'VALUE_REQUIRED', 'json' => 'VALUE_NONE']],
    ['FileShowCommand', 'file:show', 'Show file metadata', [], [], ['file'], ['json' => 'VALUE_NONE']],
    ['FileUpdateCommand', 'file:update', 'Update file metadata or access', [], [], ['file'], ['metadata' => 'VALUE_REQUIRED', 'access' => 'VALUE_REQUIRED', 'name' => 'VALUE_REQUIRED']],
    ['FileDeleteCommand', 'file:delete', 'Delete a file record and/or storage asset', ['file:remove'], [], ['file'], ['db-only' => 'VALUE_NONE', 'storage-only' => 'VALUE_NONE', 'force' => 'VALUE_NONE']],
    ['FilePurgeCommand', 'file:purge', 'Bulk-delete files by group or age', ['file:cleanup'], [], [], ['group' => 'VALUE_REQUIRED', 'older-than' => 'VALUE_REQUIRED', 'force' => 'VALUE_NONE']],
    // Pinion
    ['PinionListCommand', 'pinion:list', 'List Pinion upload sessions', [], [], [], ['status' => 'VALUE_REQUIRED', 'json' => 'VALUE_NONE']],
    ['PinionInfoCommand', 'pinion:info', 'Show a Pinion upload session', [], [], ['upload_id'], ['json' => 'VALUE_NONE']],
    ['PinionCleanCommand', 'pinion:clean', 'Clean expired or abort a Pinion upload session', [], [], [], ['abort' => 'VALUE_REQUIRED']],
];

$outDir = dirname(__DIR__) . '/src/Command';

foreach ($commands as [$class, $name, $description, $aliases, $options, $arguments, $opts]) {
    $aliasAttr = $aliases === [] ? '' : "\n    aliases: ['" . implode("', '", $aliases) . "'],";
    $optionLines = '';
    $optionNames = [];

    foreach ($opts as $optName => $mode) {
        $shortcut = match ($optName) {
            'user' => "'u'",
            'lifetime' => "'l'",
            'key' => "'k'",
            'use' => "'u'",
            default => 'null',
        };
        $flags = match ($mode) {
            'VALUE_NONE' => 'InputOption::VALUE_NONE',
            'VALUE_IS_ARRAY' => 'InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY',
            default => 'InputOption::VALUE_REQUIRED',
        };
        $optionLines .= "            ->addOption('{$optName}', {$shortcut}, {$flags})\n";
        $optionNames[] = $optName;
    }

    $argLines = '';
    foreach ($arguments as $arg) {
        $required = in_array($arg, ['role', 'permission', 'token', 'file', 'user'], true)
            ? 'InputArgument::REQUIRED'
            : 'InputArgument::OPTIONAL';
        $argLines .= "            ->addArgument('{$arg}', {$required})\n";
    }

    $optionNamesExport = $optionNames === []
        ? '[]'
        : "['" . implode("', '", $optionNames) . "']";
    $argumentNamesExport = $arguments === []
        ? '[]'
        : "['" . implode("', '", array_values($arguments)) . "']";

    $content = <<<PHP
<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\ForwardsPincoreCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: '{$name}',
    description: '{$description}',{$aliasAttr}
)]
final class {$class} extends Command
{
    use ForwardsPincoreCommand;

    protected function configure(): void
    {
        \$this
{$argLines}{$optionLines}            ->setHelp('Example: pinx {$name}');
    }

    protected function execute(InputInterface \$input, OutputInterface \$output): int
    {
        return \$this->forwardPincoreCommand(
            new SymfonyStyle(\$input, \$output),
            \$input,
            \$output,
            '{$name}',
            {$optionNamesExport},
            {$argumentNamesExport},
        );
    }
}

PHP;

    file_put_contents($outDir . '/' . $class . '.php', $content);
    echo "Wrote {$class}.php\n";
}
