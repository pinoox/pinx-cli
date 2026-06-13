<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\PinxReleaseChecker;
use Pinoox\PinxCli\Support\PinxReleaseStatus;
use Pinoox\PinxCli\Support\PinxVersion;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'version',
    description: 'Show pinx CLI version and check for updates',
)]
final class VersionCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('offline', 'o', InputOption::VALUE_NONE, 'Skip online update check')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output machine-readable JSON')
            ->addOption('refresh', null, InputOption::VALUE_NONE, 'Bypass cached release metadata')
            ->setHelp(
                <<<'HELP'
Shows the installed pinx version, install location, and whether a newer release is available.

Examples:
  pinx version
  pinx version --offline
  pinx version --json
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $offline = (bool) $input->getOption('offline');
        $status = $offline
            ? PinxReleaseStatus::offline(PinxVersion::version())
            : (new PinxReleaseChecker())->check((bool) $input->getOption('refresh'));

        if ($input->getOption('json')) {
            $output->writeln(json_encode(
                $status->toArray(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));

            return Command::SUCCESS;
        }

        $io = new SymfonyStyle($input, $output);
        $this->renderReport($io, $status);

        return Command::SUCCESS;
    }

    private function renderReport(SymfonyStyle $io, PinxReleaseStatus $status): void
    {
        $io->title('pinx CLI');

        $rows = [
            ['Version', '<info>' . $status->current . '</info>'],
            ['Install', PinxVersion::installLabel()],
            ['PHP', PHP_VERSION],
        ];

        if ($status->checkSucceeded && $status->latest !== null) {
            $rows[] = ['Latest', '<comment>' . $status->latest . '</comment>'];
        }

        $rows[] = ['Status', $this->formatStatus($status)];

        $io->table(['', ''], $rows);

        if ($status->updateAvailable && $status->latest !== null) {
            $io->newLine();
            $io->warning([
                'A newer pinx release is available: ' . $status->latest,
                'Run pinx self-update to upgrade.',
            ]);
        } elseif ($status->checkSucceeded && PinxVersion::isDevelopmentBuild($status->current) && $status->latest !== null) {
            $io->note('Development build detected. Latest stable release on Packagist: ' . $status->latest . '.');
        } elseif ($status->checkSucceeded && $status->aheadOfRelease) {
            $io->note('Installed version is newer than the latest Packagist release (' . $status->latest . ').');
        } elseif ($status->checkSucceeded && !$status->updateAvailable && PinxVersion::isStable($status->current)) {
            $io->success('You are running the latest pinx release.');
        } elseif (!$status->checkSucceeded && $status->error !== null) {
            $io->note('Update check failed: ' . rtrim($status->error, '.') . '. Use --offline to skip this check.');
        }
    }

    private function formatStatus(PinxReleaseStatus $status): string
    {
        if (!$status->checkSucceeded) {
            return '<fg=gray>Update check unavailable</>';
        }

        if (PinxVersion::isDevelopmentBuild($status->current)) {
            return '<fg=cyan;options=bold>Development build</>';
        }

        if ($status->aheadOfRelease) {
            return '<fg=cyan;options=bold>Ahead of Packagist release</>';
        }

        if ($status->updateAvailable) {
            return '<fg=yellow;options=bold>⚠ Update available</>';
        }

        return '<fg=green;options=bold>✓ Up to date</>';
    }
}
