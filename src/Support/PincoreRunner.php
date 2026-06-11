<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

final class PincoreRunner
{
    public function __construct(
        private readonly string $projectRoot,
    ) {
    }

    /**
     * @param list<string> $args
     * @param array<string, string> $extraEnv
     */
    public function run(array $args, OutputInterface $output, array $extraEnv = []): int
    {
        $binary = $this->binary();
        $command = array_merge(['php', $binary], $args);
        $env = array_merge($_ENV, DevApp::pincoreEnv($this->projectRoot), $extraEnv);

        $process = new Process($command, $this->projectRoot, $env, null, null);
        $process->setTty(Process::isTtySupported() && $output->isVerbose());

        return $process->run(function (string $type, string $buffer) use ($output): void {
            $output->write($buffer);
        });
    }

    public function binary(): string
    {
        $corePath = CorePath::resolve($this->projectRoot);

        foreach ([
            $corePath . '/bin/pincore',
            $this->projectRoot . '/vendor/bin/pincore',
        ] as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        throw new \RuntimeException('pinoox/pincore is not installed. Run: composer install');
    }
}
