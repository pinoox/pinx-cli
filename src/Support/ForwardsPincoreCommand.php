<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

trait ForwardsPincoreCommand
{
    use RunsForApp;

    /**
     * @param list<string> $optionNames
     * @param list<string> $argumentNames
     */
    protected function forwardPincoreCommand(
        SymfonyStyle $io,
        InputInterface $input,
        OutputInterface $output,
        string $pincoreCommand,
        array $optionNames = [],
        array $argumentNames = [],
        bool $appendPackage = true,
    ): int {
        $context = $this->requireApp($io);

        if ($context === null) {
            return Command::FAILURE;
        }

        $args = [$pincoreCommand];
        $hasPackageTarget = false;

        foreach ($argumentNames as $name) {
            if (!$input->hasArgument($name)) {
                continue;
            }

            $value = $input->getArgument($name);

            if ($value === null || $value === '') {
                continue;
            }

            if ($name === 'package' || $name === 'target') {
                $hasPackageTarget = true;
            }

            $args[] = is_scalar($value) ? (string) $value : '';
        }

        if ($appendPackage && !$hasPackageTarget) {
            $args[] = $context->package;
        }

        $args = array_merge($args, $this->forwardOptions($input, $optionNames));

        return $this->runPincore($context, $args, $output);
    }
}
