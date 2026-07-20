<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

/**
 * Display order and section titles for {@see \Pinoox\PinxCli\Command\ListCommand}.
 */
final class CommandCatalog
{
    /**
     * @return list<array{key: string, label: string, description: string}>
     */
    public static function sections(): array
    {
        return [
            [
                'key' => 'project',
                'label' => 'Project',
                'description' => 'Scaffold and inspect the single-app project',
            ],
            [
                'key' => 'develop',
                'label' => 'Development',
                'description' => 'Local server and day-to-day workflow',
            ],
            [
                'key' => 'database',
                'label' => 'Database',
                'description' => 'Connections, migrations, and seeders',
            ],
            [
                'key' => 'patches',
                'label' => 'Patches',
                'description' => 'Data patches and one-off app updates',
            ],
            [
                'key' => 'build',
                'label' => 'Build & release',
                'description' => 'Package and ship .pinx artifacts',
            ],
            [
                'key' => 'scaffold',
                'label' => 'Scaffolding',
                'description' => 'Generate controllers, models, tests, and more',
            ],
            [
                'key' => 'users',
                'label' => 'Users',
                'description' => 'Create and manage app users from CLI',
            ],
            [
                'key' => 'access',
                'label' => 'Roles & permissions',
                'description' => 'Manage roles and permission keys',
            ],
            [
                'key' => 'tokens',
                'label' => 'Tokens',
                'description' => 'Session tokens and API keys',
            ],
            [
                'key' => 'files',
                'label' => 'Files',
                'description' => 'Uploaded files and storage records',
            ],
            [
                'key' => 'routes',
                'label' => 'Routes',
                'description' => 'Named actions and route diagnostics',
            ],
            [
                'key' => 'deps',
                'label' => 'Dependencies',
                'description' => 'Composer and npm dependency tooling',
            ],
            [
                'key' => 'frontend',
                'label' => 'Frontend',
                'description' => 'Theme assets, Vite, and npm scripts',
            ],
            [
                'key' => 'schedule',
                'label' => 'Schedule',
                'description' => 'Cron tasks from schedule.php',
            ],
            [
                'key' => 'pinker',
                'label' => 'Pinker',
                'description' => 'Build cache and override management',
            ],
            [
                'key' => 'quality',
                'label' => 'Quality & docs',
                'description' => 'Tests and API documentation',
            ],
            [
                'key' => 'meta',
                'label' => 'Meta',
                'description' => 'CLI information',
            ],
            [
                'key' => 'other',
                'label' => 'Other',
                'description' => 'Additional commands',
            ],
        ];
    }

    /**
     * @return array<string, string> command name => section key
     */
    public static function commandSections(): array
    {
        return [
            'new' => 'project',
            'init' => 'project',
            'sync' => 'project',
            'repair' => 'project',
            'setup' => 'project',
            'doctor' => 'project',
            'info' => 'project',

            'serve' => 'develop',
            'dev' => 'develop',
            'inspector' => 'develop',

            'migrate' => 'database',
            'migrate:run' => 'database',
            'migrate:rollback' => 'database',
            'migrate:status' => 'database',
            'migrate:create' => 'database',
            'migrate:platform' => 'database',
            'migrate:reset' => 'database',
            'migrate:drop' => 'database',
            'migrate:fresh' => 'database',
            'seeder:run' => 'database',
            'seed' => 'database',

            'db:list' => 'database',
            'databases' => 'database',
            'db:show' => 'database',
            'database:show' => 'database',
            'db:test' => 'database',
            'database:test' => 'database',
            'db:create' => 'database',
            'database:create' => 'database',
            'make:db' => 'database',
            'db:update' => 'database',
            'database:update' => 'database',
            'db:prefix' => 'database',
            'database:prefix' => 'database',
            'devdb:status' => 'database',
            'devdb:clear' => 'database',
            'devdb:export' => 'database',
            'devdb:inspect' => 'database',
            'devdb:seed' => 'database',

            'patch' => 'patches',
            'patch:run' => 'patches',
            'patch:status' => 'patches',
            'patch:rollback' => 'patches',
            'patch:reset' => 'patches',
            'patch:create' => 'patches',
            'patch:clear' => 'patches',

            'build' => 'build',
            'release' => 'build',

            'make' => 'scaffold',
            'make:scaffold' => 'scaffold',

            'route:actions' => 'routes',
            'routes' => 'routes',

            'user:create' => 'users',
            'make:user' => 'users',
            'user:login' => 'users',
            'user:logout' => 'users',
            'user:list' => 'users',
            'users' => 'users',
            'user:show' => 'users',
            'user:update' => 'users',
            'user:status' => 'users',
            'user:password' => 'users',
            'user:passwd' => 'users',
            'user:delete' => 'users',
            'user:role' => 'users',
            'user:role:assign' => 'users',

            'role:list' => 'access',
            'roles' => 'access',
            'role:create' => 'access',
            'make:role' => 'access',
            'role:show' => 'access',
            'role:update' => 'access',
            'role:delete' => 'access',
            'role:permission' => 'access',
            'role:permissions' => 'access',

            'permission:list' => 'access',
            'permissions' => 'access',
            'permission:create' => 'access',
            'make:permission' => 'access',
            'permission:show' => 'access',
            'permission:delete' => 'access',

            'token:list' => 'tokens',
            'tokens' => 'tokens',
            'token:show' => 'tokens',
            'token:create' => 'tokens',
            'make:token' => 'tokens',
            'token:update' => 'tokens',
            'token:delete' => 'tokens',
            'token:remove' => 'tokens',
            'token:revoke-user' => 'tokens',
            'token:revoke' => 'tokens',
            'token:purge' => 'tokens',
            'token:cleanup' => 'tokens',

            'file:list' => 'files',
            'files' => 'files',
            'file:show' => 'files',
            'file:update' => 'files',
            'file:delete' => 'files',
            'file:remove' => 'files',
            'file:purge' => 'files',
            'file:cleanup' => 'files',

            'deps' => 'deps',
            'dep' => 'deps',
            'deps:status' => 'deps',
            'deps:install' => 'deps',
            'deps:update' => 'deps',

            'frontend' => 'frontend',
            'fe' => 'frontend',
            'fe:info' => 'frontend',
            'fe:install' => 'frontend',
            'fe:build' => 'frontend',
            'fe:dev' => 'frontend',
            'fe:watch' => 'frontend',
            'fe:dev:apps' => 'frontend',
            'fe:dev-apps' => 'frontend',
            'fe:scaffold' => 'frontend',

            'schedule:list' => 'schedule',
            'schedule:run' => 'schedule',

            'pinker' => 'pinker',
            'pinker:status' => 'pinker',
            'pinker:rebuild' => 'pinker',
            'pinker:diff' => 'pinker',
            'pinker:clear' => 'pinker',
            'pinker:overrides' => 'pinker',

            'test' => 'quality',
            'pest' => 'quality',
            'api:docs' => 'quality',
            'graphql:docs' => 'quality',

            'version' => 'meta',
            'list' => 'meta',
            'help' => 'meta',
            'completion' => 'meta',
        ];
    }

    public static function sectionFor(string $commandName): string
    {
        $map = self::commandSections();

        if (isset($map[$commandName])) {
            return $map[$commandName];
        }

        if (str_contains($commandName, ':')) {
            $prefix = explode(':', $commandName, 2)[0];

            foreach ($map as $name => $section) {
                if (str_starts_with($name, $prefix . ':')) {
                    return $section;
                }
            }
        }

        return 'other';
    }

    /**
     * Short aliases merged into each command at registration (canonical name => aliases).
     *
     * @return array<string, list<string>>
     */
    public static function aliases(): array
    {
        return [
            // Project
            'doctor' => ['dr'],
            'info' => ['inf'],

            // Database — migrate:run is canonical; migrate is the shorthand
            'migrate:run' => ['migrate'],
            'migrate:status' => ['migrate:st'],
            'migrate:rollback' => ['migrate:rb'],
            'migrate:create' => ['migrate:cr'],
            'migrate:platform' => ['migrate:pl'],
            'migrate:reset' => ['migrate:rs'],
            'migrate:drop' => ['migrate:dp'],
            'migrate:fresh' => ['migrate:fr'],
            'patch:status' => ['patch:st'],
            'patch:rollback' => ['patch:rb'],
            'patch:reset' => ['patch:rs'],
            'patch:create' => ['patch:cr'],

            'db:list' => ['databases'],
            'db:show' => ['database:show'],
            'db:test' => ['database:test'],
            'db:create' => ['database:create', 'make:db'],
            'db:update' => ['database:update'],
            'db:prefix' => ['database:prefix'],
            'devdb:status' => ['devdb:st'],
            'devdb:clear' => ['devdb:cl'],
            'devdb:inspect' => ['devdb:show'],

            // Build & release
            'build' => ['bld'],
            'release' => ['rel'],

            // Scaffolding
            'make' => ['mk'],

            // Users
            'user:list' => ['users'],
            'user:password' => ['user:passwd'],
            'user:create' => ['make:user'],
            'user:role' => ['user:role:assign'],

            'role:list' => ['roles'],
            'role:create' => ['make:role'],
            'role:permission' => ['role:permissions'],

            'permission:list' => ['permissions'],
            'permission:create' => ['make:permission'],

            'token:list' => ['tokens'],
            'token:create' => ['make:token'],
            'token:delete' => ['token:remove'],
            'token:revoke-user' => ['token:revoke'],
            'token:purge' => ['token:cleanup'],

            'file:list' => ['files'],
            'file:delete' => ['file:remove'],
            'file:purge' => ['file:cleanup'],

            // Dependencies
            'deps:status' => ['deps:st'],
            'deps:install' => ['deps:i'],
            'deps:update' => ['deps:up'],

            // Frontend
            'fe:info' => ['fe:inf'],
            'fe:install' => ['fe:i'],
            'fe:build' => ['fe:b'],
            'fe:dev' => ['fe:d'],
            'fe:watch' => ['fe:w'],
            'fe:dev:apps' => ['fe:dev-apps'],
            'fe:scaffold' => ['fe:sc'],

            // Schedule
            'schedule:list' => ['sched:ls'],
            'schedule:run' => ['sched:run'],

            // Pinker
            'pinker:status' => ['pinker:st'],
            'pinker:rebuild' => ['pinker:rb'],
            'pinker:diff' => ['pinker:df'],
            'pinker:clear' => ['pinker:cl'],
            'pinker:overrides' => ['pinker:ov'],

            // Quality & docs
            'api:docs' => ['api:doc'],
            'graphql:docs' => ['gql:doc'],

            // Meta
            'version' => ['ver'],
        ];
    }

    /**
     * @return list<string>
     */
    public static function aliasesFor(string $commandName): array
    {
        return self::aliases()[$commandName] ?? [];
    }

    /**
     * Commands omitted from the grouped pinx list (still available via help).
     *
     * @return list<string>
     */
    public static function hiddenFromList(): array
    {
        return [
            'help',
            'completion',
            'list',
            'deps',
            'frontend',
            'pinker',
        ];
    }

    /**
     * Shorthand aliases shown beside the command name in pinx list.
     *
     * @param list<string> $registeredAliases
     *
     * @return list<string>
     */
    public static function displayAliasesFor(string $commandName, array $registeredAliases): array
    {
        $aliases = self::aliasesFor($commandName);

        foreach ($registeredAliases as $alias) {
            if (str_contains($alias, ':')) {
                continue;
            }

            if (!in_array($alias, $aliases, true)) {
                $aliases[] = $alias;
            }
        }

        return $aliases;
    }
}
