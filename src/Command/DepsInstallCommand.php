<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\DepsPincoreActionCommand;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'deps:install',
    description: 'Install Composer and npm dependencies across the project',
)]
final class DepsInstallCommand extends DepsPincoreActionCommand
{
    public function __construct()
    {
        parent::__construct(
            name: 'deps:install',
            description: 'Install Composer and npm dependencies across the project',
            defaultArgv: [],
            forwardOptionNames: [],
            help: 'Example: pinx deps:install platform',
        );
    }

    protected function configureOptions(): void
    {
        $this->configureDepsInstallUpdateOptions();
    }

    protected function depsAction(): string
    {
        return 'install';
    }

    /**
     * @return list<string>
     */
    protected function depsForwardOptionNames(): array
    {
        return self::depsForwardOptionNames();
    }
}
