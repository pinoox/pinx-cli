<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\ProjectRoot;
use Pinoox\PinxCli\Support\Studio\StudioServer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'studio',
    description: 'Start Pinx Studio, a local development dashboard for the current app',
)]
final class StudioCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Studio host', '127.0.0.1')
            ->addOption('port', null, InputOption::VALUE_REQUIRED, 'Studio port', '8010')
            ->addOption('open', 'o', InputOption::VALUE_NONE, 'Open Studio in the browser');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $root = ProjectRoot::require();
            $server = new StudioServer();
            $host = (string) $input->getOption('host');
            $port = $server->findPort($host, (int) $input->getOption('port'));
            $url = $server->url($host, $port);
            $process = $server->process($root, $host, $port);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success('Pinx Studio started.');
        $io->text($url);

        if ($input->getOption('open')) {
            $server->openBrowser($url);
        }

        return $process->run(function (string $type, string $buffer) use ($output): void {
            if ($output->isVerbose()) {
                $output->write($buffer);
            }
        });
    }
}
