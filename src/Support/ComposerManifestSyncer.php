<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

final class ComposerManifestSyncer
{
    /**
     * @var list<string>
     */
    private const DEPENDENCY_SECTIONS = ['require', 'require-dev'];

    /**
     * @param array{name: string, version?: string, description?: string, type: string} $metadata
     */
    public function initializeMetadata(string $projectFile, array $metadata): void
    {
        $project = $this->decode($projectFile, 'project');

        foreach ($metadata as $key => $value) {
            if ($value !== '') {
                $project[$key] = $value;
            }
        }

        $this->write($projectFile, $project);
    }

    public function sync(string $templateFile, string $projectFile): bool
    {
        if (!is_file($templateFile) || !is_file($projectFile)) {
            return false;
        }

        $template = $this->decode($templateFile, 'template');
        $project = $this->decode($projectFile, 'project');
        $changed = false;

        foreach (self::DEPENDENCY_SECTIONS as $section) {
            $dependencies = $template[$section] ?? [];

            if (!is_array($dependencies)) {
                continue;
            }

            if (!isset($project[$section]) || !is_array($project[$section])) {
                $project[$section] = [];
            }

            foreach ($dependencies as $package => $constraint) {
                if (!is_string($package) || array_key_exists($package, $project[$section])) {
                    continue;
                }

                $project[$section][$package] = $constraint;
                $changed = true;
            }
        }

        if (!$changed) {
            return false;
        }

        $this->write($projectFile, $project);

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $file, string $label): array
    {
        $contents = file_get_contents($file);

        if (!is_string($contents)) {
            throw new \RuntimeException('Unable to read ' . $label . ' Composer manifest: ' . $file);
        }

        try {
            $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException(
                'Invalid JSON in ' . $label . ' Composer manifest: ' . $file . ' (' . $e->getMessage() . ')',
                previous: $e,
            );
        }

        if (!is_array($decoded)) {
            throw new \RuntimeException('Composer manifest must contain a JSON object: ' . $file);
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function write(string $file, array $manifest): void
    {
        $encoded = json_encode(
            $manifest,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );

        if (file_put_contents($file, $encoded . PHP_EOL, LOCK_EX) === false) {
            throw new \RuntimeException('Unable to update Composer manifest: ' . $file);
        }
    }
}
