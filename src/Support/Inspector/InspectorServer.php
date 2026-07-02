<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support\Inspector;

use Pinoox\PinxCli\Support\ProjectRoot;
use Symfony\Component\Process\Process;

final class InspectorServer
{
    public function router(?string $projectRoot = null): string
    {
        if ($projectRoot !== null) {
            $autoload = ProjectRoot::normalize($projectRoot) . '/vendor/autoload.php';
            if (is_file($autoload)) {
                require_once $autoload;
            }
        }

        if (class_exists(\Pinoox\PinxInspector\InspectorPackage::class)) {
            return \Pinoox\PinxInspector\InspectorPackage::router();
        }

        $monorepoRouter = dirname(__DIR__, 4) . '/pinx-inspector/resources/router.php';
        if (is_file($monorepoRouter)) {
            return $monorepoRouter;
        }

        throw new \RuntimeException('Pinx Inspector is not installed. Run composer require --dev pinoox/pinx-inspector.');
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

        throw new \RuntimeException('No available Inspector port found near ' . $preferredPort . '.');
    }

    public function url(string $host, int $port): string
    {
        return 'http://' . $host . ':' . $port;
    }

    public function process(string $projectRoot, string $host, int $port): Process
    {
        $this->assertLocalHost($host);

        $router = $this->router($projectRoot);

        if (!is_file($router)) {
            throw new \RuntimeException('Pinx Inspector router was not found: ' . $router);
        }

        return new Process(
            [PHP_BINARY, '-S', $host . ':' . $port, $router],
            ProjectRoot::normalize($projectRoot),
            [
                'PINX_INSPECTOR_PROJECT_ROOT' => ProjectRoot::normalize($projectRoot),
                'PINX_INSPECTOR_HOST' => $host,
                'PINX_INSPECTOR_PORT' => (string) $port,
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
            throw new \RuntimeException('Pinx Inspector is local-only. Use 127.0.0.1, localhost, or ::1.');
        }
    }
}
