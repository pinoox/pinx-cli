<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

use Symfony\Component\Console\Output\OutputInterface;

final class ProjectScaffolder
{
    public const TEMPLATE_PACKAGE = 'com_pinoox_app';
    public const TEMPLATE_DISPLAY_NAME = 'Pinoox App';
    public const TEMPLATE_DEVELOPER = 'Pinoox App Developer';
    public const TEMPLATE_DESCRIPTION = 'Pinoox App starter — built with Pinoox';

    /**
     * @param array<string, string> $replacements
     */
    public function createProject(string $targetDir, array $replacements, OutputInterface $output): void
    {
        if (TemplatePath::hasLocal()) {
            $this->copySkeleton($targetDir, $replacements);

            return;
        }

        $this->createFromComposerPackage($targetDir, $replacements, $output);
    }

    /**
     * @param array<string, string> $replacements
     */
    public function createFromComposerPackage(string $targetDir, array $replacements, OutputInterface $output): void
    {
        $targetDir = ProjectRoot::normalize($targetDir);

        if (is_dir($targetDir) && $this->directoryHasFiles($targetDir)) {
            throw new \RuntimeException('Target directory is not empty: ' . $targetDir);
        }

        $parent = dirname($targetDir);
        $name = basename($targetDir);

        if (!is_dir($parent)) {
            mkdir($parent, 0777, true);
        }

        $code = ComposerRunner::run(
            ['create-project', TemplatePath::TEMPLATE_PACKAGE, $name, '--no-install', '--no-interaction'],
            $parent,
            $output,
        );

        if ($code !== 0 || !is_file($targetDir . '/app.php')) {
            throw new \RuntimeException(
                'composer create-project ' . TemplatePath::TEMPLATE_PACKAGE . ' failed. '
                . 'Ensure Packagist access and try: composer create-project ' . TemplatePath::TEMPLATE_PACKAGE . ' ' . $name,
            );
        }

        $this->applyReplacements($targetDir, $replacements);
        $this->writeMinimalEnv($targetDir, overwrite: true);
    }

    /**
     * @param array<string, string> $replacements
     */
    public function copySkeleton(string $targetDir, array $replacements = []): void
    {
        $source = TemplatePath::resolve();
        $targetDir = ProjectRoot::normalize($targetDir);

        if (is_dir($targetDir) && $this->directoryHasFiles($targetDir)) {
            throw new \RuntimeException('Target directory is not empty: ' . $targetDir);
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $this->copyDirectory($source, $targetDir, $replacements);
        $this->writeMinimalEnv($targetDir, overwrite: true);
    }

    /**
     * @param array<string, string> $replacements
     */
    public function initInPlace(string $projectRoot, array $replacements, ?OutputInterface $output = null): void
    {
        $source = TemplatePath::resolve($output);
        $projectRoot = ProjectRoot::normalize($projectRoot);

        $skip = ['composer.json', 'composer.lock', 'vendor', '.git', 'README.md'];

        foreach (scandir($source) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            if (in_array($entry, $skip, true)) {
                continue;
            }

            $from = $source . '/' . $entry;
            $to = $projectRoot . '/' . $entry;

            if (is_dir($from)) {
                if (!is_dir($to)) {
                    $this->copyDirectory($from, $to, $replacements);
                }
                continue;
            }

            if (!is_file($to)) {
                $this->copyFile($from, $to, $replacements);
            }
        }

        $this->ensureMinimalEnv($projectRoot);
    }

    /**
     * @param array<string, string> $replacements
     * @return list<string>
     */
    public function syncInPlace(
        string $projectRoot,
        array $replacements,
        bool $overwrite = false,
        ?OutputInterface $output = null,
    ): array {
        return $this->syncSupportFiles($projectRoot, $replacements, $overwrite, $output);
    }

    /**
     * Sync only Pinx infrastructure files — never app.php, routes, or composer.json.
     *
     * @param array<string, string> $replacements
     * @return list<string>
     */
    public function syncSupportFiles(
        string $projectRoot,
        array $replacements,
        bool $overwrite = false,
        ?OutputInterface $output = null,
    ): array {
        $source = TemplatePath::resolve($output);
        $projectRoot = ProjectRoot::normalize($projectRoot);
        $changed = [];

        foreach (self::supportSyncFiles() as $relative) {
            if ($this->copySupportFile($source, $projectRoot, $relative, $replacements, $overwrite)) {
                $changed[] = $relative;
            }
        }

        return $changed;
    }

    /**
     * @param array<string, string> $replacements
     */
    public function copySupportFileIfMissing(
        string $projectRoot,
        string $relative,
        array $replacements,
        ?OutputInterface $output = null,
    ): bool {
        $source = TemplatePath::resolve($output);

        return $this->copySupportFile(
            $source,
            ProjectRoot::normalize($projectRoot),
            $relative,
            $replacements,
            overwrite: false,
        );
    }

    /**
     * @param array<string, string> $replacements
     */
    private function copySupportFile(
        string $sourceRoot,
        string $projectRoot,
        string $relative,
        array $replacements,
        bool $overwrite,
    ): bool {
        $from = $sourceRoot . '/' . $relative;
        $to = $projectRoot . '/' . $relative;

        if (!is_file($from)) {
            return false;
        }

        if (is_file($to) && !$overwrite) {
            return false;
        }

        $this->copyFile($from, $to, $replacements);

        return true;
    }

    public function ensureMinimalEnv(string $projectRoot, bool $overwrite = false): void
    {
        $this->writeMinimalEnv($projectRoot, $overwrite);
    }

    public function ensureRouterActionsClass(string $projectRoot, string $package): ?string
    {
        $projectRoot = ProjectRoot::normalize($projectRoot);
        $actionsFile = $projectRoot . '/routes/actions.php';
        $target = $projectRoot . '/Router/Actions.php';

        if (!is_file($actionsFile) || is_file($target)) {
            return null;
        }

        $contents = file_get_contents($actionsFile);

        if (!is_string($contents) || !self::usesRouterActionsClass($contents)) {
            return null;
        }

        $directory = dirname($target);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($target, self::renderRouterActionsClass($package, self::extractActionConstants($contents)));

        return 'Router/Actions.php';
    }

    /**
     * @return list<string>
     */
    public static function supportSyncFiles(): array
    {
        return [
            '.env.example',
            '.gitignore',
            '.htaccess',
            'bin/pinx',
            'index.php',
            'platform/app-router.config.php',
            'platform/apps.config.php',
            'platform/domain.config.php',
            'platform/launcher/bootstrap.php',
            'platform/launcher/server.php',
            'platform/pinoox.config.php',
        ];
    }

    /**
     * @return list<string>
     */
    public static function runtimeDirectories(): array
    {
        return [
            'storage',
            'storage/logs',
            'storage/sessions',
            'storage/devdb',
            'pinker',
        ];
    }

    /**
     * @return list<string>
     */
    private function syncFiles(): array
    {
        return [
            ...self::supportSyncFiles(),
            'app.php',
            'composer.json',
            'config/app.config.php',
            'resource/.gitkeep',
            'resource/icon.png',
            'routes/actions.php',
            'routes/web.php',
            'schedule.php',
        ];
    }

    private static function usesRouterActionsClass(string $contents): bool
    {
        return str_contains($contents, 'Router\\Actions')
            || str_contains($contents, 'Router/Actions')
            || preg_match('/\bActions::[A-Z_][A-Z0-9_]*\b/', $contents) === 1;
    }

    /**
     * @return array<string, string>
     */
    private static function extractActionConstants(string $contents): array
    {
        preg_match_all('/Actions::([A-Z_][A-Z0-9_]*)/', $contents, $matches);

        /** @var list<string> $names */
        $names = array_values(array_unique($matches[1] ?? []));

        if ($names === []) {
            return ['HOME' => 'home'];
        }

        $constants = [];

        foreach ($names as $name) {
            $constants[$name] = self::guessActionName($name, $contents);
        }

        return $constants;
    }

    private static function guessActionName(string $constant, string $contents): string
    {
        $pattern = "/action\\s*\\(\\s*Actions::{$constant}\\s*,/";

        if (preg_match($pattern, $contents) === 1) {
            return strtolower($constant);
        }

        if (preg_match("/action\\s*\\(\\s*['\"]([^'\"]+)['\"]\\s*,/", $contents, $match) === 1) {
            return (string) ($match[1] ?? strtolower($constant));
        }

        return strtolower($constant);
    }

    /**
     * @param array<string, string> $constants
     */
    private static function renderRouterActionsClass(string $package, array $constants): string
    {
        $constantLines = '';
        $allLines = '';

        foreach ($constants as $name => $value) {
            $safeValue = addslashes($value);
            $constantLines .= "    public const {$name} = '{$safeValue}';\n\n";
            $allLines .= "            self::{$name},\n";
        }

        return <<<PHP
<?php

namespace App\\{$package}\\Router;

/**
 * Named route action identifiers for this app.
 */
final class Actions
{
{$constantLines}    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
{$allLines}        ];
    }
}

PHP;
    }

    /**
     * @param array<string, string> $replacements
     */
    private function copyDirectory(string $source, string $target, array $replacements): void
    {
        if (!is_dir($target)) {
            mkdir($target, 0777, true);
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $relative = substr($item->getPathname(), strlen($source) + 1);
            $relative = str_replace('\\', '/', $relative);

            if ($this->shouldSkipSkeletonPath($relative)) {
                continue;
            }

            $destination = $target . '/' . $relative;

            if ($item->isDir()) {
                if (!is_dir($destination)) {
                    mkdir($destination, 0777, true);
                }
                continue;
            }

            if (!$item->isFile()) {
                continue;
            }

            $this->copyFile($item->getPathname(), $destination, $replacements);
        }
    }

    /**
     * @param array<string, string> $replacements
     */
    private function copyFile(string $source, string $destination, array $replacements): void
    {
        $directory = dirname($destination);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $contents = file_get_contents($source);

        if (!is_string($contents)) {
            throw new \RuntimeException('Unable to read template file: ' . $source);
        }

        if ($replacements !== []) {
            $contents = self::applyReplacementMap($contents, $replacements);
        }

        file_put_contents($destination, $contents);
    }

    private function directoryHasFiles(string $dir): bool
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            return true;
        }

        return false;
    }

    private function writeMinimalEnv(string $projectRoot, bool $overwrite = false): void
    {
        $envPath = rtrim(ProjectRoot::normalize($projectRoot), '/\\') . '/.env';

        if (!$overwrite && is_file($envPath)) {
            return;
        }

        file_put_contents($envPath, "APP_ENV=development\nDB_CONNECTION=devdb\n");
    }

    private function shouldSkipSkeletonPath(string $relative): bool
    {
        $first = explode('/', ltrim($relative, '/'), 2)[0] ?? '';

        return in_array($first, [
            '.git',
            '.idea',
            '.pinx-build',
            '.vscode',
            'export',
            'node_modules',
            'pinker',
            'vendor',
        ], true)
            || $relative === 'composer.lock'
            || str_ends_with($relative, '.log');
    }

    public static function defaultReplacements(string $package, string $displayName = '', string $developer = ''): array
    {
        if ($displayName === '') {
            $displayName = self::displayNameFromPackage($package);
        }

        if ($developer === '') {
            $developer = 'Developer';
        }

        $description = $displayName . ' — built with Pinoox';

        return [
            self::TEMPLATE_DESCRIPTION => $description,
            self::TEMPLATE_PACKAGE => $package,
            self::TEMPLATE_DISPLAY_NAME => $displayName,
            self::TEMPLATE_DEVELOPER => $developer,
            '__PINX_DESCRIPTION__' => $description,
            '__PINX_PACKAGE__' => $package,
            '__PINX_DISPLAY_NAME__' => $displayName,
            '__PINX_DEVELOPER__' => $developer,
        ];
    }

    public static function displayNameFromPackage(string $package): string
    {
        $parts = explode('_', $package);
        $name = end($parts) ?: $package;

        return ucfirst(str_replace('-', ' ', $name));
    }

    /**
     * @param array<string, string> $replacements
     */
    public function copyFileFromSkeleton(string $relativePath, string $destination, array $replacements = [], ?OutputInterface $output = null): void
    {
        $source = TemplatePath::resolve($output) . '/' . ltrim($relativePath, '/');
        $this->copyFile($source, $destination, $replacements);
    }

    /**
     * @param array<string, string> $replacements
     */
    public function applyReplacements(string $projectRoot, array $replacements): void
    {
        if ($replacements === []) {
            return;
        }

        $projectRoot = ProjectRoot::normalize($projectRoot);
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($projectRoot, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            if (!$item->isFile() || $this->isBinaryTemplateFile($item->getPathname())) {
                continue;
            }

            $contents = file_get_contents($item->getPathname());

            if (!is_string($contents) || !self::containsReplacementKey($contents, $replacements)) {
                continue;
            }

            file_put_contents(
                $item->getPathname(),
                self::applyReplacementMap($contents, $replacements),
            );
        }
    }

    /**
     * @param array<string, string> $replacements
     */
    public static function applyReplacementMap(string $contents, array $replacements): string
    {
        if ($replacements === []) {
            return $contents;
        }

        uksort($replacements, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return str_replace(array_keys($replacements), array_values($replacements), $contents);
    }

    /**
     * @param array<string, string> $replacements
     */
    private static function containsReplacementKey(string $contents, array $replacements): bool
    {
        foreach (array_keys($replacements) as $key) {
            if (str_contains($contents, $key)) {
                return true;
            }
        }

        return false;
    }

    public static function suggestPackageFromDirectory(string $name): string
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '_', $slug) ?? $slug;
        $slug = preg_replace('/_+/', '_', $slug) ?? $slug;
        $slug = trim($slug, '_');

        if ($slug === '') {
            return 'com_my_app';
        }

        /** @var list<string> $parts */
        $parts = array_values(array_filter(
            explode('_', $slug),
            static fn (string $part): bool => $part !== '',
        ));

        $prefixes = ['com', 'ir', 'org', 'net', 'io'];

        if (count($parts) >= 3 && in_array($parts[0], $prefixes, true)) {
            return $parts[0] . '_' . $parts[1] . '_' . $parts[2];
        }

        if (count($parts) === 2) {
            if ($parts[0] === $parts[1]) {
                return 'com_my_' . $parts[1];
            }

            return 'com_' . $parts[0] . '_' . $parts[1];
        }

        if (count($parts) >= 3) {
            $vendor = $parts[0];
            $app = $parts[count($parts) - 1];

            if ($vendor === $app) {
                $app = $parts[1];
            }

            return 'com_' . $vendor . '_' . $app;
        }

        return 'com_my_' . $parts[0];
    }

    public static function normalizePackage(string $input): string
    {
        $input = trim($input);

        if ($input === '') {
            throw new \InvalidArgumentException('Package name is required.');
        }

        $input = strtolower($input);
        $input = preg_replace('/[^a-z0-9_]+/', '_', $input) ?? $input;
        $input = preg_replace('/_+/', '_', $input) ?? $input;
        $input = trim($input, '_');

        if ($input === '') {
            throw new \InvalidArgumentException('Package name is required.');
        }

        if (!preg_match('/^[a-z][a-z0-9]*(_[a-z][a-z0-9_]*)+$/', $input)) {
            throw new \InvalidArgumentException(
                'Package must be lowercase with underscores and at least two segments '
                . '(e.g. com_acme_shop).',
            );
        }

        return $input;
    }

    private function isBinaryTemplateFile(string $path): bool
    {
        return (bool) preg_match('/\.(png|jpe?g|gif|webp|ico|zip|phar)$/i', $path);
    }
}
