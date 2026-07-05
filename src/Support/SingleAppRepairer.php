<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

use Symfony\Component\Console\Output\OutputInterface;

final class SingleAppRepairer
{
    public function __construct(
        private readonly ProjectRepairInspector $inspector = new ProjectRepairInspector(),
    ) {
    }

    /**
     * @return list<string>
     */
    public function sync(
        string $projectRoot,
        string $package = '',
        string $displayName = '',
        string $developer = '',
        bool $overwrite = false,
        ?OutputInterface $output = null,
    ): array {
        $projectRoot = ProjectRoot::normalize($projectRoot);
        $package = $this->resolvePackage($projectRoot, $package);
        $replacements = ProjectScaffolder::defaultReplacements($package, $displayName, $developer);

        return (new ProjectScaffolder())->syncSupportFiles($projectRoot, $replacements, $overwrite, $output);
    }

    /**
     * @return array{changed: list<string>, findings: list<RepairFinding>, remaining: list<RepairFinding>}
     */
    public function repair(
        string $projectRoot,
        string $package = '',
        string $displayName = '',
        string $developer = '',
        bool $overwrite = false,
        ?OutputInterface $output = null,
    ): array {
        $projectRoot = ProjectRoot::normalize($projectRoot);
        $package = $this->resolvePackage($projectRoot, $package);
        $replacements = ProjectScaffolder::defaultReplacements($package, $displayName, $developer);
        $context = AppContext::find($projectRoot);
        $findings = $this->inspector->diagnose($projectRoot, $package, $context);
        $changed = $this->inspector->fix(
            $findings,
            $projectRoot,
            $package,
            $replacements,
            $overwrite,
            output: $output,
        );

        $remaining = $this->remainingFindings(
            $this->inspector->diagnose($projectRoot, $package, $context),
        );

        return [
            'changed' => array_values(array_unique($changed)),
            'findings' => $findings,
            'remaining' => $remaining,
        ];
    }

    public function resolvePackage(string $projectRoot, string $package = ''): string
    {
        $package = trim($package);

        if ($package !== '') {
            return ProjectScaffolder::normalizePackage($package);
        }

        $detected = DevApp::package($projectRoot);

        if (is_string($detected) && $detected !== '') {
            return ProjectScaffolder::normalizePackage($detected);
        }

        return ProjectScaffolder::suggestPackageFromDirectory(basename(ProjectRoot::normalize($projectRoot)));
    }

    /**
     * @param list<RepairFinding> $findings
     * @return list<RepairFinding>
     */
    private function remainingFindings(array $findings): array
    {
        return array_values(array_filter(
            $findings,
            static fn (RepairFinding $finding): bool => $finding->fixable,
        ));
    }
}
