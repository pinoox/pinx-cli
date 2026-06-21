<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

trait RunsForApp
{
    protected function requireApp(SymfonyStyle $io): ?AppContext
    {
        try {
            return AppContext::require();
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return null;
        }
    }

    protected function runner(AppContext $context): PincoreRunner
    {
        return new PincoreRunner($context->root);
    }

    /**
     * @param list<string> $args
     */
    protected function runPincore(AppContext $context, array $args, OutputInterface $output): int
    {
        $code = $this->runner($context)->run($args, $output);

        return $code === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @param list<string> $optionNames
     * @return list<string>
     */
    protected function forwardOptions(InputInterface $input, array $optionNames): array
    {
        $args = [];

        foreach ($optionNames as $name) {
            if (!$input->hasOption($name)) {
                continue;
            }

            $value = $input->getOption($name);

            if ($value === false || $value === null || $value === '') {
                continue;
            }

            if ($value === true) {
                $args[] = '--' . $name;

                continue;
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    if ($item === null || $item === '') {
                        continue;
                    }
                    $args[] = '--' . $name . '=' . (string) $item;
                }

                continue;
            }

            $args[] = '--' . $name . '=' . (string) $value;
        }

        return $args;
    }
}
