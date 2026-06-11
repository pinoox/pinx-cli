<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\AppContext;
use Pinoox\PinxCli\Support\PincoreActionCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'deps:install',
    description: 'Install Composer and npm dependencies for the app',
)]
final class DepsInstallCommand extends PincoreActionCommand
{
    public function __construct()
    {
        parent::__construct(
            name: 'deps:install',
            description: 'Install Composer and npm dependencies for the app',
            defaultArgv: [],
            forwardOptionNames: ['composer-only', 'npm-only', 'theme', 'all-themes', 'production', 'no-ci', 'plain', 'continue-on-error'],
            help: 'Example: pinx deps:install --npm-only',
        );
    }

    protected function configureOptions(): void
    {
        $this->addForwardOptions([
            ['composer-only', null, InputOption::VALUE_NONE, 'Only Composer targets'],
            ['npm-only', null, InputOption::VALUE_NONE, 'Only npm targets'],
            ['theme', null, InputOption::VALUE_REQUIRED, 'Theme folder name'],
            ['all-themes', null, InputOption::VALUE_NONE, 'Include every theme with package.json'],
            ['production', null, InputOption::VALUE_NONE, 'Composer without dev dependencies'],
            ['no-ci', null, InputOption::VALUE_NONE, 'npm install instead of ci'],
            ['plain', null, InputOption::VALUE_NONE, 'Plain output for CI'],
            ['continue-on-error', null, InputOption::VALUE_NONE, 'Continue when a step fails'],
        ]);
    }

    /**
     * @return list<string>
     */
    protected function pincoreArgv(AppContext $context, InputInterface $input): array
    {
        return ['deps', 'install', $context->package];
    }
}
