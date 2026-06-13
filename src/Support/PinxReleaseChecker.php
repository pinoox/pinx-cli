<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

use Symfony\Component\Process\Process;

final class PinxReleaseChecker
{
    private const PACKAGIST_P2 = 'https://repo.packagist.org/p2/pinoox/pinx-cli.json';
    private const CACHE_TTL = 3600;

    public function check(bool $forceRefresh = false): PinxReleaseStatus
    {
        $current = PinxVersion::version();

        if (PinxVersion::isDevelopmentBuild($current)) {
            $latest = $this->fetchLatestStable($forceRefresh);

            if ($latest === null) {
                return PinxReleaseStatus::failed($current, 'Could not reach Packagist.');
            }

            return new PinxReleaseStatus(
                current: $current,
                latest: $latest,
                updateAvailable: false,
                checkSucceeded: true,
            );
        }

        $latest = $this->fetchLatestStable($forceRefresh);

        if ($latest === null) {
            return PinxReleaseStatus::failed($current, 'Could not reach Packagist.');
        }

        $updateAvailable = version_compare(
            PinxVersion::normalize($current),
            PinxVersion::normalize($latest),
            '<',
        );

        $aheadOfRelease = version_compare(
            PinxVersion::normalize($current),
            PinxVersion::normalize($latest),
            '>',
        ) && PinxVersion::isStable($current);

        return new PinxReleaseStatus(
            current: $current,
            latest: $latest,
            updateAvailable: $updateAvailable,
            checkSucceeded: true,
            aheadOfRelease: $aheadOfRelease,
        );
    }

    private function fetchLatestStable(bool $forceRefresh): ?string
    {
        $cached = $this->readCache($forceRefresh);

        if ($cached !== null) {
            return $cached;
        }

        $body = $this->httpGet(self::PACKAGIST_P2);

        if ($body === null) {
            $latest = $this->fetchLatestViaComposer();

            if ($latest !== null) {
                $this->writeCache($latest);
            }

            return $latest;
        }

        $data = json_decode($body, true);

        if (!is_array($data)) {
            return null;
        }

        $entries = $data['packages']['pinoox/pinx-cli'] ?? null;

        if (!is_array($entries)) {
            return null;
        }

        $latest = null;

        foreach ($entries as $meta) {
            if (!is_array($meta)) {
                continue;
            }

            $version = $meta['version'] ?? null;

            if (!is_string($version) || !PinxVersion::isStable($version)) {
                continue;
            }

            if ($latest === null || version_compare(
                PinxVersion::normalize($version),
                PinxVersion::normalize($latest),
                '>',
            )) {
                $latest = $version;
            }
        }

        if ($latest !== null) {
            $this->writeCache($latest);
        }

        return $latest;
    }

    private function readCache(bool $forceRefresh): ?string
    {
        if ($forceRefresh) {
            return null;
        }

        $file = $this->cacheFile();

        if (!is_file($file)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($file), true);

        if (!is_array($data)) {
            return null;
        }

        if (time() > (int) ($data['expires'] ?? 0)) {
            return null;
        }

        $version = $data['latest'] ?? null;

        return is_string($version) && $version !== '' ? $version : null;
    }

    private function writeCache(string $latest): void
    {
        $payload = json_encode([
            'latest' => $latest,
            'expires' => time() + self::CACHE_TTL,
        ], JSON_UNESCAPED_SLASHES);

        if (!is_string($payload)) {
            return;
        }

        @file_put_contents($this->cacheFile(), $payload);
    }

    private function cacheFile(): string
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pinx-cli-release.json';
    }

    private function httpGet(string $url): ?string
    {
        $body = $this->httpGetViaStream($url);

        if ($body !== null) {
            return $body;
        }

        return $this->httpGetViaCurl($url);
    }

    private function httpGetViaStream(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'header' => 'User-Agent: pinoox/pinx-cli/' . PinxVersion::version() . "\r\n",
            ],
            'ssl' => CaBundle::streamSslOptions(),
        ]);

        $body = @file_get_contents($url, false, $context);

        return is_string($body) && $body !== '' ? $body : null;
    }

    private function httpGetViaCurl(string $url): ?string
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $handle = curl_init($url);

        if ($handle === false) {
            return null;
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT => 'pinoox/pinx-cli/' . PinxVersion::version(),
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        CaBundle::applyToCurl($handle);

        $body = curl_exec($handle);
        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if (!is_string($body) || $body === '' || $status >= 400) {
            return null;
        }

        return $body;
    }

    private function fetchLatestViaComposer(): ?string
    {
        $attempts = [];

        if (PinxVersion::isGlobalInstall()) {
            $attempts[] = [null, ['composer', 'global', 'show', 'pinoox/pinx-cli', '--latest', '--format=json', '--no-ansi']];
        }

        $projectRoot = PinxVersion::projectRootFromInstall();

        if ($projectRoot !== null) {
            $attempts[] = [$projectRoot, ['composer', 'show', 'pinoox/pinx-cli', '--latest', '--format=json', '--no-ansi']];
        }

        if ($attempts === []) {
            $attempts[] = [null, ['composer', 'show', 'pinoox/pinx-cli', '--latest', '--format=json', '--no-ansi', '--available']];
        }

        foreach ($attempts as [$cwd, $command]) {
            $process = new Process($command, $cwd, null, null, 30);
            $process->run();

            if (!$process->isSuccessful()) {
                continue;
            }

            $data = json_decode($process->getOutput(), true);

            if (!is_array($data)) {
                continue;
            }

            $latest = $data['latest'] ?? null;

            if (is_string($latest) && $latest !== '' && PinxVersion::isStable($latest)) {
                return $latest;
            }
        }

        return null;
    }
}
