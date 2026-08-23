<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

trait RequiresPinroll
{
    protected function requirePinroll(SymfonyStyle $io, AppContext $context): bool
    {
        $pinroll = $context->root . '/vendor/pinoox/pinroll/src/Pinroll.php';
        if (is_file($pinroll) || class_exists(\Pinoox\Pinroll\Pinroll::class, false)) {
            return true;
        }

        $io->error('Pinroll is not installed. Run: composer require --dev pinoox/pinroll');

        return false;
    }

    protected function pinrollMissing(): int
    {
        return Command::FAILURE;
    }
}
