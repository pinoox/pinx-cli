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
        $args = self::ensureAnsiArgs($args);
        $corePath = CorePath::resolve($this->projectRoot);
        $binary = $this->binary($corePath);
        $command = array_merge(['php'], CliErrorReporting::phpIniArgs($this->projectRoot), [$binary], $args);
        $env = array_merge($_ENV, [
            'PINOOX_BASE_PATH' => $this->projectRoot,
            'PINOOX_CORE_PATH' => $corePath,
        ], DevApp::pincoreEnv($this->projectRoot), $extraEnv);

        if (CliTerminalStyle::supportsColor() || CliErrorReporting::shouldDisplayErrors($this->projectRoot)) {
            $env['FORCE_COLOR'] = '1';
            $env['CLICOLOR_FORCE'] = '1';
        }

        foreach (['COLORTERM', 'TERM', 'COLUMNS', 'LINES', 'ANSICON'] as $key) {
            $value = getenv($key);

            if (is_string($value) && $value !== '') {
                $env[$key] = $value;
            }
        }

        $useTty = Process::isTtySupported()
            && is_resource(STDOUT)
            && @stream_isatty(STDOUT)
            && $output->isVerbose();

        $process = new Process($command, $this->projectRoot, $env, null, null);
        $process->setTty($useTty);

        if ($useTty) {
            $exitCode = $process->run();
        } else {
            $exitCode = $process->run(static function (string $type, string $buffer): void {
                $stream = $type === Process::ERR ? STDERR : STDOUT;

                if (is_resource($stream)) {
                    fwrite($stream, $buffer);

                    return;
                }

                echo $buffer;
            });
        }

        if ($exitCode !== 0 && trim($process->getOutput() . $process->getErrorOutput()) === '') {
            $this->renderSilentFailure($exitCode, $args);
        }

        return $exitCode;
    }

    /**
     * @param list<string> $args
     * @return list<string>
     */
    private static function ensureAnsiArgs(array $args): array
    {
        if (in_array('--no-ansi', $args, true) || in_array('--quiet', $args, true) || in_array('-q', $args, true)) {
            return $args;
        }

        if (!CliTerminalStyle::supportsColor()) {
            return $args;
        }

        if (in_array('--ansi', $args, true)) {
            return $args;
        }

        return array_merge(['--ansi'], $args);
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
