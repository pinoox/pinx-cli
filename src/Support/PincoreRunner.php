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
        $corePath = CorePath::resolve($this->projectRoot);
        $binary = $this->binary($corePath);
        $command = array_merge(['php'], CliErrorReporting::phpIniArgs($this->projectRoot), [$binary], $args);
        $env = array_merge($_ENV, [
            'PINOOX_BASE_PATH' => $this->projectRoot,
            'PINOOX_CORE_PATH' => $corePath,
        ], DevApp::pincoreEnv($this->projectRoot), $extraEnv);

        if (CliErrorReporting::shouldDisplayErrors($this->projectRoot)) {
            $env['FORCE_COLOR'] = '1';
        }

        $process = new Process($command, $this->projectRoot, $env, null, null);
        $process->setTty(Process::isTtySupported() && $output->isVerbose());

        $exitCode = $process->run(static function (string $type, string $buffer): void {
            $stream = $type === Process::ERR ? STDERR : STDOUT;

            if (is_resource($stream)) {
                fwrite($stream, $buffer);

                return;
            }

            echo $buffer;
        });

        if ($exitCode !== 0 && trim($process->getOutput() . $process->getErrorOutput()) === '') {
            $this->renderSilentFailure($exitCode, $args);
        }

        return $exitCode;
    }

    public function binary(?string $corePath = null): string
    {
        $corePath ??= CorePath::resolve($this->projectRoot);

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

    /**
     * @param list<string> $args
     */
    private function renderSilentFailure(int $exitCode, array $args): void
    {
        $style = new CliTerminalStyle();
        $command = 'pincore ' . implode(' ', $args);
        $lines = [
            '',
            $style->banner('Pincore failed', '1;97', '41'),
            $style->rule(),
            $style->wrap('The pincore subprocess exited with code ' . $exitCode . ' without any output.'),
            '',
            $style->field('Command', $command),
        ];

        if (CliErrorReporting::shouldDisplayErrors($this->projectRoot)) {
            $lines[] = '';
            $lines[] = $style->section('Try');
            $lines[] = '';
            $lines[] = '  ' . $style->color('>', '1;96') . ' ' . $style->accent(
                'php ' . implode(' ', CliErrorReporting::phpIniArgs($this->projectRoot))
                . ' vendor/pinoox/pincore/bin/pincore ' . implode(' ', $args),
            );
        } else {
            $lines[] = '';
            $lines[] = $style->bullet('Hint', 'Set APP_ENV=development or PINOOX_EXCEPTION=true in .env to show CLI errors.');
        }

        $lines[] = '';
        fwrite(STDERR, implode(PHP_EOL, $lines));
    }
}
