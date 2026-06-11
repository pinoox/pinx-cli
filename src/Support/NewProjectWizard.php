<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

use Symfony\Component\Console\Style\SymfonyStyle;

final class NewProjectWizard
{
    public function __construct(
        private readonly SymfonyStyle $io,
    ) {
    }

    /**
     * @return array{target: string, package: string, displayName: string, developer: string}
     */
    public function runForNew(
        ?string $directory,
        ?string $package,
        ?string $displayName,
        ?string $developer,
        bool $skipConfirm = false,
    ): array {
        $this->io->title('Pinx — Create new app');

        if ($directory === null || $directory === '') {
            $this->io->section('Step 1/4 — Project directory');
            $this->io->text('Folder for your new project (created relative to the current directory unless you pass a path).');
            $directory = (string) $this->io->ask('Directory', 'my-app');
        }

        $target = $this->resolveTargetPath($directory);

        $package = $this->resolvePackage($package, basename($target), 2, 4);
        $displayName = $this->resolveDisplayName($displayName, $package, 3, 4);
        $developer = $this->resolveDeveloper($developer, 4, 4);

        $this->renderSummary($target, $package, $displayName, $developer);

        if (!$skipConfirm && !$this->io->confirm('Create this project?', true)) {
            throw new WizardCancelledException();
        }

        return [
            'target' => $target,
            'package' => $package,
            'displayName' => $displayName,
            'developer' => $developer,
        ];
    }

    /**
     * @return array{package: string, displayName: string, developer: string}
     */
    public function runForInit(
        ?string $package,
        ?string $displayName,
        ?string $developer,
        bool $skipConfirm = false,
    ): array {
        $this->io->title('Pinx — Initialize app');

        $package = $this->resolvePackage($package, 'my_app', 1, 3);
        $displayName = $this->resolveDisplayName($displayName, $package, 2, 3);
        $developer = $this->resolveDeveloper($developer, 3, 3);

        $this->io->section('Summary');
        $this->io->definitionList(
            ['Package' => $package],
            ['App name' => $displayName],
            ['Developer' => $developer],
        );

        if (!$skipConfirm && !$this->io->confirm('Initialize this directory?', true)) {
            throw new WizardCancelledException();
        }

        return [
            'package' => $package,
            'displayName' => $displayName,
            'developer' => $developer,
        ];
    }

    private function resolveTargetPath(string $directory): string
    {
        $directory = ProjectRoot::normalize($directory);

        if (str_contains($directory, '/') || preg_match('/^[A-Za-z]:/', $directory)) {
            return $directory;
        }

        return ProjectRoot::normalize((getcwd() ?: '.') . '/' . $directory);
    }

    private function resolvePackage(?string $package, string $defaultBase, int $step, int $total): string
    {
        if ($package !== null && $package !== '') {
            return ProjectScaffolder::normalizePackage($package);
        }

        $this->io->section(sprintf('Step %d/%d — Package name', $step, $total));
        $this->io->text([
            'Unique app identifier: lowercase letters, numbers, and underscores.',
            'Examples: <comment>com_acme_shop</comment>',
        ]);

        return $this->askPackage(ProjectScaffolder::suggestPackageFromDirectory($defaultBase));
    }

    private function resolveDisplayName(?string $displayName, string $package, int $step, int $total): string
    {
        if ($displayName !== null && $displayName !== '') {
            return $displayName;
        }

        $this->io->section(sprintf('Step %d/%d — Display name', $step, $total));
        $this->io->text('Human-readable app title shown in Manager and docs.');

        return (string) $this->io->ask('App name', ProjectScaffolder::displayNameFromPackage($package));
    }

    private function resolveDeveloper(?string $developer, int $step, int $total): string
    {
        if ($developer !== null && $developer !== '') {
            return $developer;
        }

        $this->io->section(sprintf('Step %d/%d — Developer', $step, $total));
        $this->io->text('Author or team name stored in app.php.');

        return (string) $this->io->ask('Developer / author', 'Developer');
    }

    private function askPackage(string $default): string
    {
        while (true) {
            $input = (string) $this->io->ask('Package', $default);

            try {
                return ProjectScaffolder::normalizePackage($input);
            } catch (\InvalidArgumentException $e) {
                $this->io->warning($e->getMessage());
            }
        }
    }

    private function renderSummary(string $target, string $package, string $displayName, string $developer): void
    {
        $this->io->section('Summary');
        $this->io->definitionList(
            ['Directory' => $target],
            ['Package' => $package],
            ['App name' => $displayName],
            ['Developer' => $developer],
            ['Template' => TemplatePath::sourceLabel()],
        );
    }
}
