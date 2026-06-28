<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

use Symfony\Component\Console\Output\OutputInterface;

final class SingleAppRepairer
{
    /**
     * @return list<string>
     */
    public function sync(
        string $projectRoot,
        string $package = '',
        string $displayName = '',
        string $developer = '',
        bool $overwrite = false,
        ?OutputInterface $output = null,
    ): array {
        $projectRoot = ProjectRoot::normalize($projectRoot);
        $package = $this->resolvePackage($projectRoot, $package);
        $replacements = ProjectScaffolder::defaultReplacements($package, $displayName, $developer);

        return (new ProjectScaffolder())->syncInPlace($projectRoot, $replacements, $overwrite, $output);
    }

    /**
     * @return list<string>
     */
    public function repair(
        string $projectRoot,
        string $package = '',
        string $displayName = '',
        string $developer = '',
        bool $overwrite = false,
        ?OutputInterface $output = null,
    ): array {
        $projectRoot = ProjectRoot::normalize($projectRoot);
        $package = $this->resolvePackage($projectRoot, $package);
        $changed = $this->sync($projectRoot, $package, $displayName, $developer, $overwrite, $output);

        foreach ($this->ensureRuntimeDirectories($projectRoot) as $path) {
            $changed[] = $path;
        }

        if ($this->ensureRegistryMapping($projectRoot, $package)) {
            $changed[] = ProjectPaths::appsRegistryRelativeLabel($projectRoot);
        }

        return array_values(array_unique($changed));
    }

    public function resolvePackage(string $projectRoot, string $package = ''): string
    {
        $package = trim($package);

        if ($package !== '') {
            return ProjectScaffolder::normalizePackage($package);
        }

        $detected = DevApp::package($projectRoot);

        if (is_string($detected) && $detected !== '') {
            return ProjectScaffolder::normalizePackage($detected);
        }

        return ProjectScaffolder::suggestPackageFromDirectory(basename(ProjectRoot::normalize($projectRoot)));
    }

    /**
     * @return list<string>
     */
    private function ensureRuntimeDirectories(string $projectRoot): array
    {
        $changed = [];

        foreach ([
            'storage',
            'storage/logs',
            'storage/sessions',
            'storage/devdb',
            'pinker',
            'export',
        ] as $relative) {
            $path = $projectRoot . '/' . $relative;

            if (is_dir($path)) {
                continue;
            }

            mkdir($path, 0777, true);
            $changed[] = $relative;
        }

        return $changed;
    }

    private function ensureRegistryMapping(string $projectRoot, string $package): bool
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
}
