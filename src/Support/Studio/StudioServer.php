<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support\Studio;

use Pinoox\PinxCli\Support\ProjectRoot;
use Symfony\Component\Process\Process;

final class StudioServer
{
    public function router(): string
    {
        return dirname(__DIR__, 3) . '/resources/studio/router.php';
    }

    public function findPort(string $host, int $preferredPort): int
    {
        $this->assertLocalHost($host);

        for ($port = $preferredPort; $port < $preferredPort + 50; $port++) {
            $socket = @stream_socket_server('tcp://' . $host . ':' . $port, $errno, $errstr);
            if (is_resource($socket)) {
                fclose($socket);

                return $port;
            }
        }

        throw new \RuntimeException('No available Studio port found near ' . $preferredPort . '.');
    }

    public function url(string $host, int $port): string
    {
        return 'http://' . $host . ':' . $port;
    }

    public function process(string $projectRoot, string $host, int $port): Process
    {
        $this->assertLocalHost($host);

        $router = $this->router();

        if (!is_file($router)) {
            throw new \RuntimeException('Pinx Studio router was not found: ' . $router);
        }

        return new Process(
            [PHP_BINARY, '-S', $host . ':' . $port, $router],
            ProjectRoot::normalize($projectRoot),
            [
                'PINX_STUDIO_PROJECT_ROOT' => ProjectRoot::normalize($projectRoot),
                'PINX_STUDIO_HOST' => $host,
                'PINX_STUDIO_PORT' => (string) $port,
            ],
            null,
            null,
        );
    }

    public function openBrowser(string $url): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            (new Process(['cmd', '/c', 'start', '', $url]))->start();

            return;
        }

        if (PHP_OS_FAMILY === 'Darwin') {
            (new Process(['open', $url]))->start();

            return;
        }

        (new Process(['xdg-open', $url]))->start();
    }

    private function assertLocalHost(string $host): void
    {
        if (!in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new \RuntimeException('Pinx Studio is local-only. Use 127.0.0.1, localhost, or ::1.');
        }
    }
}
