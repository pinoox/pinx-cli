<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\ForwardsPincoreCommand;
use Pinoox\PinxCli\Support\RequiresPinroll;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'pinroll:init',
    description: 'Scaffold .pinoox/pinroll.config.php overlay for this Pinx app',
)]
final class PinrollInitCommand extends Command
{
    use ForwardsPincoreCommand;
    use RequiresPinroll;

    protected function configure(): void
    {
        $this
            ->addArgument('target', InputArgument::OPTIONAL, 'Host name (default production)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Rewrite pinroll.config.php')
            ->setHelp('Requires pinoox/pinroll. Example: pinx pinroll:init');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = $this->requireApp($io);
        if ($context === null) {
            return Command::FAILURE;
        }

        if (!$this->requirePinroll($io, $context)) {
            return $this->pinrollMissing();
        }

        return $this->forwardPincoreCommand(
            $io,
            $input,
            $output,
            'pinroll:init',
            ['force'],
            ['target'],
            false,
        );
    }
}
