<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

final class ProjectScaffolder
{
    /**
     * @param array<string, string> $replacements
     */
    public function copySkeleton(string $targetDir, array $replacements = []): void
    {
        $source = TemplatePath::skeletonDir();
        $targetDir = ProjectRoot::normalize($targetDir);

        if (is_dir($targetDir) && $this->directoryHasFiles($targetDir)) {
            throw new \RuntimeException('Target directory is not empty: ' . $targetDir);
        }

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }

        $this->copyDirectory($source, $targetDir, $replacements);
    }

    /**
     * @param array<string, string> $replacements
     */
    public function initInPlace(string $projectRoot, array $replacements): void
    {
        $source = TemplatePath::skeletonDir();
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
            $destination = $target . '/' . $relative;

            if ($item->isDir()) {
                if (!is_dir($destination)) {
                    mkdir($destination, 0777, true);
                }
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
            $contents = str_replace(array_keys($replacements), array_values($replacements), $contents);
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

    public static function defaultReplacements(string $package, string $displayName = '', string $developer = ''): array
    {
        if ($displayName === '') {
            $displayName = self::displayNameFromPackage($package);
        }

        if ($developer === '') {
            $developer = 'Developer';
        }

        return [
            '__PINX_PACKAGE__' => $package,
            '__PINX_DISPLAY_NAME__' => $displayName,
            '__PINX_DEVELOPER__' => $developer,
            '__PINX_DESCRIPTION__' => $displayName . ' — built with Pinoox',
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
    public function copyFileFromSkeleton(string $relativePath, string $destination, array $replacements = []): void
    {
        $source = TemplatePath::skeletonDir() . '/' . ltrim($relativePath, '/');
        $this->copyFile($source, $destination, $replacements);
    }

    public static function normalizePackage(string $input): string
    {
        $input = trim($input);

        if ($input === '') {
            throw new \InvalidArgumentException('Package name is required.');
        }

        if (!preg_match('/^com_[a-z][a-z0-9_]*$/', $input)) {
            throw new \InvalidArgumentException('Package must match com_vendor_app (e.g. com_acme_shop).');
        }

        return $input;
    }
}
