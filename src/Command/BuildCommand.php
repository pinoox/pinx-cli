<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\DevApp;
use Pinoox\PinxCli\Support\PincoreRunner;
use Pinoox\PinxCli\Support\ProjectRoot;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'build',
    description: 'Build a .pinx install package for the current app',
)]
final class BuildCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output .pinx file path')
            ->addOption('sign', 's', InputOption::VALUE_NONE, 'Sign the package when a key is configured')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $root = ProjectRoot::require();
            $package = DevApp::requirePackage($root);
        } catch (\RuntimeException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        if (!$input->getOption('yes') && !$io->confirm('Build .pinx for ' . $package . '?', true)) {
            return Command::SUCCESS;
        }

        $args = ['pinx:build', $package, '-y'];
        $outputPath = $input->getOption('output');

        if (is_string($outputPath) && $outputPath !== '') {
            $args[] = '--output=' . $outputPath;
        }

        if ($input->getOption('sign')) {
            $args[] = '--sign';
        }

        $runner = new PincoreRunner($root);
        $code = $runner->run($args, $output);

        return $code === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
