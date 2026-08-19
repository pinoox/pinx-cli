<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\ForwardsPincoreCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'deploy',
    description: 'Build, upload, and install this app via Pinroll (php pinoox pinroll:deploy)',
    aliases: ['pinroll:deploy'],
)]
final class DeployCommand extends Command
{
    use ForwardsPincoreCommand;

    protected function configure(): void
    {
        $this
            ->addArgument('host', InputArgument::OPTIONAL, 'Pinroll host name')
            ->addOption('via', null, InputOption::VALUE_REQUIRED, 'Transport: ftp, ssh, pinion')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Push app + vendor + theme')
            ->addOption('vendor', null, InputOption::VALUE_NONE, 'Include vendor pack')
            ->addOption('theme', null, InputOption::VALUE_NONE, 'Rebuild theme assets (fe:build) then include in the app .pinx')
            ->addOption('platform', null, InputOption::VALUE_NONE, 'Also ship platform .zip (pinx:update)')
            ->addOption('app', null, InputOption::VALUE_REQUIRED, 'App package (multi-app platform)')
            ->addOption('check', 'c', InputOption::VALUE_NONE, 'Run pinroll:check first')
            ->setHelp('Requires pinoox/pinroll. Example: pinx deploy');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = $this->requireApp($io);
        if ($context === null) {
            return Command::FAILURE;
        }

        $pinroll = $context->root . '/vendor/pinoox/pinroll/src/Pinroll.php';
        if (!is_file($pinroll) && !class_exists(\Pinoox\Pinroll\Pinroll::class, false)) {
            $io->error('Pinroll is not installed. Run: composer require --dev pinoox/pinroll');

            return Command::FAILURE;
        }

        return $this->forwardPincoreCommand(
            $io,
            $input,
            $output,
            'pinroll:deploy',
            ['via', 'all', 'vendor', 'theme', 'platform', 'app', 'check'],
            ['host'],
            false,
        );
    }
}
