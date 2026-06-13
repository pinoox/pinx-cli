<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

final class CaBundle
{
    private static ?string $resolved = null;

    public static function resolve(): ?string
    {
        if (self::$resolved !== null) {
            return self::$resolved !== '' ? self::$resolved : null;
        }

        foreach (self::candidates() as $candidate) {
            if (is_file($candidate)) {
                return self::$resolved = $candidate;
            }
        }

        self::$resolved = '';

        return null;
    }

    /**
     * @return array<string, bool|string>
     */
    public static function streamSslOptions(): array
    {
        $options = [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ];

        $bundle = self::resolve();

        if ($bundle !== null) {
            $options['cafile'] = $bundle;
        }

        return $options;
    }

    /**
     * @param resource|\CurlHandle $handle
     */
    public static function applyToCurl($handle): void
    {
        $bundle = self::resolve();

        if ($bundle !== null) {
            curl_setopt($handle, CURLOPT_CAINFO, $bundle);

            return;
        }

        if (PHP_OS_FAMILY === 'Windows' && defined('CURLSSLOPT_NATIVE_CA')) {
            curl_setopt($handle, CURLOPT_SSL_OPTIONS, CURLSSLOPT_NATIVE_CA);
        }
    }

    /**
     * @return list<string>
     */
    private static function candidates(): array
    {
        $candidates = [];

        foreach (['openssl.cafile', 'curl.cainfo'] as $iniKey) {
            $value = ini_get($iniKey);

            if (!is_string($value) || $value === '') {
                continue;
            }

            $candidates[] = $value;
            array_push($candidates, ...self::expandBrokenMampPath($value));
        }

        foreach (['SSL_CERT_FILE', 'CURL_CA_BUNDLE'] as $envKey) {
            $value = getenv($envKey);

            if (is_string($value) && $value !== '') {
                $candidates[] = $value;
            }
        }

        if (class_exists(\Composer\CaBundle\CaBundle::class)) {
            $candidates[] = \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath();
        }

        array_push($candidates, ...self::commonFallbackPaths());

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * @return list<string>
     */
    private static function expandBrokenMampPath(string $path): array
    {
        if (!str_contains($path, 'MAMP_BASEDIR')) {
            return [];
        }

        $normalized = str_replace('\\', '/', $path);
        $suffix = preg_replace('#^.*?MAMP_BASEDIR(?:_MAMP)?/?#', '', $normalized) ?? '';
        $suffix = ltrim($suffix, '/');

        $expanded = [];

        foreach (self::mampRoots() as $root) {
            if ($suffix !== '') {
                $expanded[] = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $suffix);
            }

            $expanded[] = $root
                . DIRECTORY_SEPARATOR . 'bin'
                . DIRECTORY_SEPARATOR . 'apache'
                . DIRECTORY_SEPARATOR . 'bin'
                . DIRECTORY_SEPARATOR . 'cacert.pem';
        }

        return $expanded;
    }

    /**
     * @return list<string>
     */
    private static function commonFallbackPaths(): array
    {
        $paths = [];

        foreach (self::mampRoots() as $root) {
            $paths[] = $root
                . DIRECTORY_SEPARATOR . 'bin'
                . DIRECTORY_SEPARATOR . 'apache'
                . DIRECTORY_SEPARATOR . 'bin'
                . DIRECTORY_SEPARATOR . 'cacert.pem';
        }

        if (preg_match('#^(.*?[/\\\\]MAMP)[/\\\\]#i', PHP_BINARY, $matches) === 1) {
            $paths[] = $matches[1]
                . DIRECTORY_SEPARATOR . 'bin'
                . DIRECTORY_SEPARATOR . 'apache'
                . DIRECTORY_SEPARATOR . 'bin'
                . DIRECTORY_SEPARATOR . 'cacert.pem';
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    private static function mampRoots(): array
    {
        $roots = [];

        foreach (['C:', 'D:', 'E:'] as $drive) {
            $roots[] = $drive . DIRECTORY_SEPARATOR . 'MAMP';
        }

        return $roots;
    }
}
