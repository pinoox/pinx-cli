<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\AppContext;
use Pinoox\PinxCli\Support\Doctor\CheckItem;
use Pinoox\PinxCli\Support\Doctor\CheckStatus;
use Pinoox\PinxCli\Support\Doctor\DoctorReport;
use Pinoox\PinxCli\Support\Doctor\DoctorRunner;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'doctor',
    description: 'Deep health check: PHP, app layout, env, database, frontend, and build readiness',
)]
final class DoctorCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output machine-readable JSON report')
            ->addOption('skip-db', null, InputOption::VALUE_NONE, 'Skip database connectivity checks')
            ->addOption('skip-frontend', null, InputOption::VALUE_NONE, 'Skip Node/npm and frontend checks')
            ->addOption('no-fixes', null, InputOption::VALUE_NONE, 'Hide suggested fix commands')
            ->setHelp(
                <<<'HELP'
Runs a structured diagnostic on the current single-app Pinoox project.

Examples:
  pinx doctor
  pinx doctor --skip-db
  pinx doctor --json
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = AppContext::find();
        $runner = new DoctorRunner(
            skipDatabase: (bool) $input->getOption('skip-db'),
            skipFrontend: (bool) $input->getOption('skip-frontend'),
        );
        $report = $runner->run($context);

        if ($input->getOption('json')) {
            $output->writeln(json_encode(
                $report->toArray($this->appMeta($context)),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ));

            return $report->isHealthy() ? Command::SUCCESS : Command::FAILURE;
        }

        $io = new SymfonyStyle($input, $output);
        $this->renderReport($io, $report, $context, !(bool) $input->getOption('no-fixes'));

        return $report->isHealthy() ? Command::SUCCESS : Command::FAILURE;
    }

    private function renderReport(
        SymfonyStyle $io,
        DoctorReport $report,
        ?AppContext $context,
        bool $showFixes,
    ): void {
        $io->title('Pinx Doctor');

        if ($context !== null) {
            $this->renderHeader($io, $context, $report);
        }

        foreach ($report->groups() as $group) {
            $items = $report->forGroup($group);

            if ($items === []) {
                continue;
            }

            $io->section($group);
            $io->table(
                ['', 'Check', 'Details'],
                array_map(
                    static fn (CheckItem $item): array => [
                        $item->status->icon(),
                        $item->label,
                        self::formatDetail($item),
                    ],
                    $items,
                ),
            );
        }

        $this->renderSummary($io, $report);

        if ($showFixes && $report->fixHints() !== []) {
            $io->section('Suggested fixes');
            $io->listing($report->fixHints());
        }

        if (!$report->isHealthy()) {
            $fail = $report->failCount();
            $warn = $report->warnCount();
            $parts = [];

            if ($fail > 0) {
                $parts[] = $fail . ' failure' . ($fail === 1 ? '' : 's');
            }

            if ($warn > 0) {
                $parts[] = $warn . ' warning' . ($warn === 1 ? '' : 's');
            }

            $io->error('Health check failed — fix issues above before pinx setup, dev, or build.');

            return;
        }

        if ($report->warnCount() > 0) {
            $io->warning('No blocking issues. Review warnings and suggested fixes before production.');

            return;
        }

        $io->success('All checks passed — environment is ready for development.');
    }

    private function renderHeader(SymfonyStyle $io, AppContext $context, DoctorReport $report): void
    {
        $score = $report->score();
        $bar = $this->scoreBar($score);
        $status = match (true) {
            $report->failCount() > 0 => '<fg=red;options=bold>Needs attention</>',
            $report->warnCount() > 0 => '<fg=yellow;options=bold>Mostly ready</>',
            default => '<fg=green;options=bold>Healthy</>',
        };

        $io->definitionList(
            ['App' => '<info>' . $context->displayName() . '</info>'],
            ['Package' => '<comment>' . $context->package . '</comment>'],
            ['Root' => $context->root],
            ['Health' => $status . '  ' . $bar . '  <info>' . $score . '%</info>'],
            ['Checks' => sprintf(
                '<fg=green>%d passed</> · <fg=yellow>%d warnings</> · <fg=red>%d failed</>',
                $report->passCount(),
                $report->warnCount(),
                $report->failCount(),
            )],
        );

        $io->newLine();
    }

    private function renderSummary(SymfonyStyle $io, DoctorReport $report): void
    {
        $io->section('Summary');

        $rows = [
            ['Passed', (string) $report->passCount()],
            ['Warnings', (string) $report->warnCount()],
            ['Failed', (string) $report->failCount()],
            ['Health score', $report->score() . '%'],
        ];

        $io->table(['Metric', 'Value'], $rows);

        if ($report->isHealthy() && $report->warnCount() === 0) {
            $io->text('  <fg=green>✔</> All scored checks passed.');
        } elseif ($report->isHealthy()) {
            $io->text('  <fg=yellow>!</> No blocking issues, but review warnings before production.');
        } else {
            $io->text('  <fg=red>✖</> Fix failed checks before running pinx setup, dev, or build.');
        }
    }

    private function scoreBar(int $score): string
    {
        $filled = (int) round($score / 10);
        $filled = max(0, min(10, $filled));
        $empty = 10 - $filled;

        $color = match (true) {
            $score >= 90 => 'green',
            $score >= 70 => 'yellow',
            default => 'red',
        };

        return '<fg=' . $color . '>' . str_repeat('█', $filled) . '</>'
            . '<fg=gray>' . str_repeat('░', $empty) . '</>';
    }

    private static function formatDetail(CheckItem $item): string
    {
        if ($item->detail === '') {
            return '';
        }

        if ($item->status === CheckStatus::Fail) {
            return '<fg=red>' . $item->detail . '</>';
        }

        if ($item->status === CheckStatus::Warn) {
            return '<fg=yellow>' . $item->detail . '</>';
        }

        return $item->detail;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function appMeta(?AppContext $context): ?array
    {
        if ($context === null) {
            return null;
        }

        return [
            'package' => $context->package,
            'name' => $context->displayName(),
            'root' => $context->root,
            'theme' => $context->theme(),
            'version' => $context->versionName(),
            'version_code' => $context->versionCode(),
        ];
    }
}
