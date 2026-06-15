<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support\Manifest;

/**
 * Resolve manifest label fields without booting pincore.
 *
 * Supports lang ref (@manifest.title), locale maps, and plain strings.
 */
final class ManifestLabel
{
    private const LANG_POSTFIX = '.lang.php';

    public static function isLangRef(mixed $value): bool
    {
        return ManifestLangRef::isRef($value);
    }

    public static function isLocaleMap(mixed $value): bool
    {
        if (!is_array($value) || $value === []) {
            return false;
        }

        if (array_is_list($value)) {
            return false;
        }

        foreach ($value as $key => $item) {
            if (!is_string($key) || !is_string($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $langPaths
     */
    public static function resolve(
        mixed $value,
        array $langPaths,
        ?string $locale = null,
        ?string $fallback = null,
        ?string $fallbackLocale = 'en',
    ): string {
        if (ManifestLangRef::isRef($value)) {
            $parsed = ManifestLangRef::parse($value);
            $paths = $parsed['package'] !== null
                ? self::pathsForPackage($parsed['package'], $langPaths)
                : $langPaths;

            $resolved = self::get($paths, $parsed['key'], $locale, $fallbackLocale);

            if ($resolved !== '') {
                return $resolved;
            }

            return is_string($fallback) ? $fallback : '';
        }

        if (self::isLocaleMap($value)) {
            return self::fromLocaleMap($value, $locale);
        }

        if (is_string($value)) {
            return $value;
        }

        return is_string($fallback) ? $fallback : '';
    }

    /**
     * @param list<string> $langPaths
     * @return array<string, string>
     */
    public static function collect(mixed $value, array $langPaths): array
    {
        if (ManifestLangRef::isRef($value)) {
            $parsed = ManifestLangRef::parse($value);

            return self::collectKey(
                $parsed['package'] !== null
                    ? self::pathsForPackage($parsed['package'], $langPaths)
                    : $langPaths,
                $parsed['key'],
            );
        }

        if (self::isLocaleMap($value)) {
            /** @var array<string, string> $value */
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return ['en' => $value];
        }

        return [];
    }

    /**
     * @param array<string, string> $map
     */
    public static function fromLocaleMap(array $map, ?string $locale = null): string
    {
        if ($locale !== null && $locale !== '' && isset($map[$locale]) && is_string($map[$locale])) {
            return $map[$locale];
        }

        $first = reset($map);

        return is_string($first) ? $first : '';
    }

    /**
     * @param list<string> $langPaths
     */
    public static function get(array $langPaths, string $key, ?string $locale = null, ?string $fallbackLocale = 'en'): string
    {
        if ($key === '') {
            return '';
        }

        [$group, $item] = self::parseKey($key);

        foreach ($langPaths as $path) {
            $path = rtrim(str_replace('\\', '/', $path), '/');

            if ($path === '' || !is_dir($path)) {
                continue;
            }

            foreach (self::localeCandidates($locale, $fallbackLocale) as $candidate) {
                $line = self::readLine($path, $candidate, $group, $item);

                if ($line !== '') {
                    return $line;
                }
            }
        }

        return '';
    }

    /**
     * @param list<string> $defaultPaths paths used when package-specific lang is unavailable
     * @return list<string>
     */
    private static function pathsForPackage(string $package, array $defaultPaths): array
    {
        foreach ($defaultPaths as $path) {
            if (is_dir($path)) {
                return [$path];
            }
        }

        return [];
    }

    /**
     * @param list<string> $langPaths
     * @return array<string, string>
     */
    private static function collectKey(array $langPaths, string $key): array
    {
        if ($key === '') {
            return [];
        }

        [$group, $item] = self::parseKey($key);
        $labels = [];

        foreach ($langPaths as $path) {
            $path = rtrim(str_replace('\\', '/', $path), '/');

            if ($path === '' || !is_dir($path)) {
                continue;
            }

            foreach (scandir($path) ?: [] as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }

                $localeDir = $path . '/' . $entry;

                if (!is_dir($localeDir)) {
                    continue;
                }

                $line = self::readLine($path, $entry, $group, $item);

                if ($line !== '' && !isset($labels[$entry])) {
                    $labels[$entry] = $line;
                }
            }
        }

        return $labels;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function parseKey(string $key): array
    {
        $key = trim($key);

        if ($key === '') {
            return ['', ''];
        }

        if (!str_contains($key, '.')) {
            return ['manifest', $key];
        }

        [$group, $item] = explode('.', $key, 2);

        return [trim($group), trim($item)];
    }

    /**
     * @return list<string>
     */
    private static function localeCandidates(?string $locale, ?string $fallbackLocale): array
    {
        $candidates = [];

        foreach ([$locale, $fallbackLocale, 'en'] as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            if (!in_array($candidate, $candidates, true)) {
                $candidates[] = $candidate;
            }
        }

        return $candidates;
    }

    private static function readLine(string $langRoot, string $locale, string $group, string $item): string
    {
        if ($group === '' || $item === '') {
            return '';
        }

        $file = $langRoot . '/' . $locale . '/' . $group . self::LANG_POSTFIX;

        if (!is_file($file)) {
            return '';
        }

        $data = include $file;

        if (!is_array($data) || !isset($data[$item]) || !is_string($data[$item])) {
            return '';
        }

        return $data[$item];
    }
}
