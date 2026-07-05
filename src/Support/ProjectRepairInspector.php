<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

final class ProjectRepairInspector
{
    /**
     * @return list<RepairFinding>
     */
    public function diagnose(string $projectRoot, string $package, ?AppContext $context = null): array
    {
        $projectRoot = ProjectRoot::normalize($projectRoot);
        $context ??= AppContext::find($projectRoot);
        $findings = [];

        foreach (ProjectScaffolder::supportSyncFiles() as $relative) {
            if (is_file($projectRoot . '/' . $relative)) {
                continue;
            }

            $findings[] = new RepairFinding(
                id: 'support:' . $relative,
                label: $relative,
                detail: 'Missing Pinx support file',
            );
        }

        if (!is_file($projectRoot . '/.env')) {
            $findings[] = new RepairFinding(
                id: 'env:file',
                label: '.env',
                detail: 'Missing local environment file',
            );
        }

        if (!$this->hasRegistryMapping($projectRoot, $package)) {
            $findings[] = new RepairFinding(
                id: 'registry:mapping',
                label: ProjectPaths::appsRegistryRelativeLabel($projectRoot),
                detail: 'Package "' . $package . '" is not mapped to ~',
            );
        }

        foreach ($this->missingRuntimeDirectories($projectRoot) as $relative) {
            $findings[] = new RepairFinding(
                id: 'runtime:' . $relative,
                label: $relative,
                detail: 'Missing runtime directory',
            );
        }

        if ($context !== null) {
            foreach ($this->missingRegisteredRouteFiles($projectRoot, $context) as $relative) {
                $findings[] = new RepairFinding(
                    id: 'route:' . $relative,
                    label: $relative,
                    detail: 'Registered in app.php but missing on disk',
                );
            }
        }

        if ($this->needsRouterActionsClass($projectRoot, $package)) {
            $findings[] = new RepairFinding(
                id: 'router:actions-class',
                label: 'Router/Actions.php',
                detail: 'Required by routes/actions.php but missing',
            );
        }

        return $findings;
    }

    /**
     * @param list<RepairFinding> $findings
     * @param array<string, string> $replacements
     * @return list<string>
     */
    public function fix(
        array $findings,
        string $projectRoot,
        string $package,
        array $replacements,
        bool $overwriteSupportFiles,
        ?ProjectScaffolder $scaffolder = null,
        ?\Symfony\Component\Console\Output\OutputInterface $output = null,
    ): array {
        $projectRoot = ProjectRoot::normalize($projectRoot);
        $scaffolder ??= new ProjectScaffolder();
        $changed = [];
        $ids = array_map(static fn (RepairFinding $finding): string => $finding->id, $findings);

        if ($this->hasFindingPrefix($ids, 'support:')) {
            foreach ($scaffolder->syncSupportFiles($projectRoot, $replacements, $overwriteSupportFiles, $output) as $path) {
                $changed[] = $path;
            }
        }

        if (in_array('env:file', $ids, true) && !is_file($projectRoot . '/.env')) {
            $scaffolder->ensureMinimalEnv($projectRoot);
            $changed[] = '.env';
        }

        if (in_array('registry:mapping', $ids, true) && $this->writeRegistryMapping($projectRoot, $package)) {
            $changed[] = ProjectPaths::appsRegistryRelativeLabel($projectRoot);
        }

        foreach ($findings as $finding) {
            if (!str_starts_with($finding->id, 'runtime:')) {
                continue;
            }

            $relative = substr($finding->id, strlen('runtime:'));
            $path = $projectRoot . '/' . $relative;

            if (is_dir($path)) {
                continue;
            }

            mkdir($path, 0777, true);
            $changed[] = $relative;
        }

        foreach ($findings as $finding) {
            if (!str_starts_with($finding->id, 'route:')) {
                continue;
            }

            $relative = substr($finding->id, strlen('route:'));

            if ($scaffolder->copySupportFileIfMissing($projectRoot, $relative, $replacements, $output)) {
                $changed[] = $relative;
            }
        }

        if (in_array('router:actions-class', $ids, true)) {
            $generated = $scaffolder->ensureRouterActionsClass($projectRoot, $package);

            if ($generated !== null) {
                $changed[] = $generated;
            }
        }

        return array_values(array_unique($changed));
    }

    /**
     * @param list<string> $ids
     */
    private function hasFindingPrefix(array $ids, string $prefix): bool
    {
        foreach ($ids as $id) {
            if (str_starts_with($id, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function hasRegistryMapping(string $projectRoot, string $package): bool
    {
        $registryFile = ProjectPaths::appsRegistryFile($projectRoot);

        if (!is_file($registryFile)) {
            return false;
        }

        $config = require $registryFile;

        if (!is_array($config)) {
            return false;
        }

        $packages = $config['packages'] ?? [];

        return is_array($packages) && ($packages[$package] ?? null) === '~';
    }

    private function writeRegistryMapping(string $projectRoot, string $package): bool
    {
        $registryFile = ProjectPaths::appsRegistryFile($projectRoot);
        $config = [];

        if (is_file($registryFile)) {
            $loaded = require $registryFile;
            $config = is_array($loaded) ? $loaded : [];
        }

        $packages = $config['packages'] ?? [];
        if (!is_array($packages)) {
            $packages = [];
        }

        if (($packages[$package] ?? null) === '~') {
            return false;
        }

        $packages[$package] = '~';
        $config['packages'] = $packages;

        $directory = dirname($registryFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents(
            $registryFile,
            "<?php\n\nreturn " . var_export($config, true) . ";\n",
        );

        return true;
    }

    /**
     * @return list<string>
     */
    private function missingRuntimeDirectories(string $projectRoot): array
    {
        $missing = [];

        foreach (ProjectScaffolder::runtimeDirectories() as $relative) {
            if (!is_dir($projectRoot . '/' . $relative)) {
                $missing[] = $relative;
            }
        }

        return $missing;
    }

    /**
     * @return list<string>
     */
    private function missingRegisteredRouteFiles(string $projectRoot, AppContext $context): array
    {
        $missing = [];

        foreach ($context->routeFiles() as $relative) {
            if (!is_file($projectRoot . '/' . $relative)) {
                $missing[] = $relative;
            }
        }

        return $missing;
    }

    private function needsRouterActionsClass(string $projectRoot, string $package): bool
    {
        $actionsFile = $projectRoot . '/routes/actions.php';
        $routerActionsFile = $projectRoot . '/Router/Actions.php';

        if (!is_file($actionsFile) || is_file($routerActionsFile)) {
            return false;
        }

        $contents = file_get_contents($actionsFile);

        if (!is_string($contents)) {
            return false;
        }

        return str_contains($contents, 'Router\\Actions')
            || str_contains($contents, 'Router/Actions')
            || preg_match('/\bActions::[A-Z_][A-Z0-9_]*\b/', $contents) === 1;
    }
}
