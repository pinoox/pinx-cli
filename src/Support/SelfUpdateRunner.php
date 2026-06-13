<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

use Symfony\Component\Console\Style\SymfonyStyle;

final class SelfUpdateRunner
{
    public function run(SymfonyStyle $io, bool $force): int
    {
        if (PinxVersion::isLocalSourceInstall()) {
            $io->warning([
                'pinx is running from a local source checkout.',
                'Use git pull or composer update inside packages/pinx-cli instead of self-update.',
            ]);

            return 1;
        }

        $status = (new PinxReleaseChecker())->check(forceRefresh: true);

        if (!$status->checkSucceeded) {
            $io->error($status->error ?? 'Could not determine the latest pinx release.');

            return 1;
        }

        if (!$status->updateAvailable && !$force) {
            $io->success('pinx ' . $status->current . ' is already the latest release.');

            return 0;
        }

        if ($status->updateAvailable) {
            $io->note(sprintf(
                'Updating pinx from %s to %s…',
                $status->current,
                $status->latest,
            ));
        }

        $cwd = null;
        $args = ['global', 'update', 'pinoox/pinx-cli', '--with-all-dependencies'];

        if (PinxVersion::isGlobalInstall()) {
            $io->text('Running: <comment>composer global update pinoox/pinx-cli</comment>');
        } else {
            $projectRoot = PinxVersion::projectRootFromInstall();

            if ($projectRoot === null) {
                $io->error([
                    'Could not detect the Composer project that installed pinx.',
                    'Install globally with: composer global require pinoox/pinx-cli',
                ]);

                return 1;
            }

            $cwd = $projectRoot;
            $args = ['update', 'pinoox/pinx-cli', '--with-all-dependencies'];
            $io->text('Running: <comment>composer update pinoox/pinx-cli</comment> in ' . PinxVersion::shortenPath($projectRoot));
        }

        $exitCode = ComposerRunner::run($args, $cwd, $io);

        if ($exitCode !== 0) {
            $io->error('pinx self-update failed. See Composer output above.');

            return $exitCode;
        }

        $io->success('pinx updated successfully. Run pinx version to confirm.');

        return 0;
    }
}
