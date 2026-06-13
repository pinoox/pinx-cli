<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\SelfUpdateRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'self-update',
    description: 'Update pinx CLI to the latest Packagist release',
    aliases: ['update:cli'],
)]
final class SelfUpdateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Run Composer update even if already on the latest release')
            ->setHelp(
                <<<'HELP'
Updates the globally or project-installed pinx CLI package via Composer.

Examples:
  pinx self-update
  pinx self-update --force
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        return (new SelfUpdateRunner())->run($io, (bool) $input->getOption('force'));
    }
}
