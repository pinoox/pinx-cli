<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\DevApp;
use Pinoox\PinxCli\Support\PincoreRunner;
use Pinoox\PinxCli\Support\ProjectAutoload;
use Pinoox\PinxCli\Support\ProjectRoot;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'release',
    description: 'Bump app version, build .pinx, and optionally sign for distribution',
)]
final class ReleaseCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('bump', 'b', InputOption::VALUE_REQUIRED, 'Version bump: patch, minor, major, or explicit version-name')
            ->addOption('sign', 's', InputOption::VALUE_NONE, 'Sign the release package')
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

        $appFile = $root . '/app.php';
        ProjectAutoload::boot($root);
        $config = require $appFile;

        if (!is_array($config)) {
            $io->error('Invalid app.php');

            return Command::FAILURE;
        }

        $versionName = (string) ($config['version-name'] ?? '1.0.0');
        $versionCode = (int) ($config['version-code'] ?? 1);
        $bump = (string) ($input->getOption('bump') ?: 'patch');

        [$newName, $newCode] = $this->bumpVersion($versionName, $versionCode, $bump);

        if (!$input->getOption('yes') && !$io->confirm(
            sprintf('Release %s %s (#%d → #%d)?', $package, $newName, $versionCode, $newCode),
            true,
        )) {
            return Command::SUCCESS;
        }

        $this->writeAppVersions($appFile, $newName, $newCode);
        $io->writeln('Updated app.php → version-name=' . $newName . ', version-code=' . $newCode);

        $runner = new PincoreRunner($root);
        $args = ['pinx:build', $package, '-y'];

        if ($input->getOption('sign') || !empty($config['pinx']['sign']['enabled'])) {
            $args[] = '--sign';
        }

        $code = $runner->run($args, $output);

        if ($code === 0) {
            $io->success('Release package built in pinx/releases/');
        }

        return $code === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function bumpVersion(string $name, int $code, string $bump): array
    {
        if (!in_array($bump, ['patch', 'minor', 'major'], true)) {
            return [$bump, $code + 1];
        }

        if (!preg_match('/^(\d+)\.(\d+)\.(\d+)/', $name, $matches)) {
            return ['1.0.1', $code + 1];
        }

        $major = (int) $matches[1];
        $minor = (int) $matches[2];
        $patch = (int) $matches[3];

        if ($bump === 'major') {
            $major++;
            $minor = 0;
            $patch = 0;
        } elseif ($bump === 'minor') {
            $minor++;
            $patch = 0;
        } else {
            $patch++;
        }

        return [sprintf('%d.%d.%d', $major, $minor, $patch), $code + 1];
    }

    private function writeAppVersions(string $appFile, string $versionName, int $versionCode): void
    {
        $contents = file_get_contents($appFile);

        if (!is_string($contents)) {
            throw new \RuntimeException('Unable to read app.php');
        }

        $contents = preg_replace(
            "/(['\"]version-name['\"]\s*=>\s*['\"])([^'\"]*)(['\"])/",
            '${1}' . $versionName . '${3}',
            $contents,
            1,
        ) ?? $contents;

        $contents = preg_replace(
            "/(['\"]version-code['\"]\s*=>\s*)\d+/",
            '${1}' . $versionCode,
            $contents,
            1,
        ) ?? $contents;

        file_put_contents($appFile, $contents);
    }
}
