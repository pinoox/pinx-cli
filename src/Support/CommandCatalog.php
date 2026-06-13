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
                'description' => 'Migrations and seeders',
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
            'setup' => 'project',
            'doctor' => 'project',
            'info' => 'project',

            'dev' => 'develop',

            'migrate' => 'database',
            'migrate:run' => 'database',
            'migrate:rollback' => 'database',
            'migrate:status' => 'database',
            'migrate:create' => 'database',
            'migrate:platform' => 'database',
            'seeder:run' => 'database',
            'seed' => 'database',
            'patch' => 'patches',
            'patch:run' => 'patches',
            'patch:status' => 'patches',
            'patch:rollback' => 'patches',

            'build' => 'build',
            'release' => 'build',

            'make' => 'scaffold',
            'make:scaffold' => 'scaffold',

            'route:actions' => 'routes',
            'routes' => 'routes',

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
            'self-update' => 'meta',
            'update:cli' => 'meta',
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
            'patch:status' => ['patch:st'],
            'patch:rollback' => ['patch:rb'],

            // Build & release
            'build' => ['bld'],
            'release' => ['rel'],

            // Scaffolding
            'make' => ['mk'],

            // Dependencies
            'deps:status' => ['deps:st'],
            'deps:install' => ['deps:i'],
            'deps:update' => ['deps:up'],

            // Frontend
            'fe:info' => ['fe:inf'],
            'fe:install' => ['fe:i'],
            'fe:build' => ['fe:b'],
            'fe:dev' => ['fe:d'],
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
            'self-update' => ['self:up'],
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
