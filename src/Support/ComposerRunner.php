<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

final class ComposerRunner
{
    /**
     * @param list<string> $args
     */
    public static function run(array $args, ?string $cwd, OutputInterface $output): int
    {
        $command = array_merge(self::composerBinary(), $args);
        $process = new Process($command, $cwd, null, null, 600);
        $process->setTty(Process::isTtySupported());

        return $process->run(function (string $type, string $buffer) use ($output): void {
            $output->write($buffer);
        });
    }

    /**
     * @return list<string>
     */
    private static function composerBinary(): array
    {
        $composer = getenv('COMPOSER_BINARY');

        if (is_string($composer) && $composer !== '') {
            return [$composer];
        }

        return ['composer'];
    }
}
