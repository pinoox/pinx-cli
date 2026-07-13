<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Shared argv/options for pinx deps → pincore deps forwarding.
 *
 * @phpstan-require-extends Command
 */
trait DepsForward
{
    /**
     * @return list<string>
     */
    protected static function depsForwardOptionNames(): array
    {
        return [
            'composer-only',
            'npm-only',
            'theme',
            'all-themes',
            'production',
            'no-ci',
            'plain',
            'continue-on-error',
        ];
    }

    /**
     * @return list<string>
     */
    protected static function depsStatusForwardOptionNames(): array
    {
        return [
            'composer-only',
            'npm-only',
            'theme',
            'all-themes',
        ];
    }

    protected function configureDepsPackageArgument(): void
    {
        $this->addArgument(
            'package',
            InputArgument::OPTIONAL,
            'App package, platform, or all. Leave empty to pick from the list.',
        );
    }

    /**
     * @param list<array{0: string, 1: string|null, 2: int, 3: string}> $definitions
     */
    protected function addDepsForwardOptions(array $definitions): void
    {
        foreach ($definitions as $definition) {
            $this->addOption(
                (string) $definition[0],
                $definition[1] ?? null,
                $definition[2] ?? InputOption::VALUE_NONE,
                $definition[3] ?? '',
            );
        }
    }

    protected function configureDepsInstallUpdateOptions(): void
    {
        $this->addDepsForwardOptions([
            ['composer-only', null, InputOption::VALUE_NONE, 'Only run Composer targets'],
            ['npm-only', null, InputOption::VALUE_NONE, 'Only run npm targets'],
            ['theme', null, InputOption::VALUE_REQUIRED, 'Theme folder, theme context (site, panel, …), or all'],
            ['all-themes', null, InputOption::VALUE_NONE, 'Include every theme context or theme folder with package.json'],
            ['production', null, InputOption::VALUE_NONE, 'Composer: install/update without dev dependencies'],
            ['no-ci', null, InputOption::VALUE_NONE, 'npm: use install instead of ci when package-lock.json exists'],
            ['plain', null, InputOption::VALUE_NONE, 'Plain output without step panels (CI-friendly)'],
            ['continue-on-error', null, InputOption::VALUE_NONE, 'Continue remaining targets when one step fails'],
        ]);
    }

    protected function configureDepsStatusOptions(): void
    {
        $this->addDepsForwardOptions([
            ['composer-only', null, InputOption::VALUE_NONE, 'Only run Composer targets'],
            ['npm-only', null, InputOption::VALUE_NONE, 'Only run npm targets'],
            ['theme', null, InputOption::VALUE_REQUIRED, 'Theme folder, theme context (site, panel, …), or all'],
            ['all-themes', null, InputOption::VALUE_NONE, 'Include every theme context or theme folder with package.json'],
        ]);
    }

    protected function depsHelpText(): string
    {
        return <<<'HELP'
Manage Composer (PHP) and npm (theme frontend) dependencies for the whole project or a single app.

Actions:
  status    List discovered manifests and whether vendor/node_modules exist
  install   Run composer install / npm install (or npm ci when lockfile exists)
  update    Run composer update / npm update

Scopes:
  all         Project root + every app composer.json and theme package.json
  platform    Project root composer.json only
  com_my_shop Single app composer.json + active theme package.json

Examples:
  pinx deps
  pinx deps status all
  pinx deps install platform
  pinx deps install com_my_shop
  pinx deps install com_my_shop --all-themes
  pinx deps install com_my_shop --theme=panel
  pinx deps install com_my_shop --theme=all
  pinx deps install all --production
  pinx deps update com_my_shop --composer-only

Dedicated commands:
  pinx deps:status
  pinx deps:install
  pinx deps:update

Leave action and scope empty to pick interactively.
HELP;
    }

    protected function validateDepsOptions(InputInterface $input, SymfonyStyle $io): ?int
    {
        if ((bool) $input->getOption('composer-only') && (bool) $input->getOption('npm-only')) {
            $io->error('Use only one of --composer-only or --npm-only.');

            return Command::FAILURE;
        }

        return null;
    }

    /**
     * @param list<string> $forwardOptionNames
     * @return list<string>
     */
    protected function buildDepsArgv(string $action, InputInterface $input, array $forwardOptionNames): array
    {
        $args = ['deps', $action];

        if ($input->hasArgument('package')) {
            $package = trim((string) $input->getArgument('package'));

            if ($package !== '') {
                $args[] = $package;
            }
        }

        return array_merge($args, $this->forwardOptions($input, $forwardOptionNames));
    }
}
