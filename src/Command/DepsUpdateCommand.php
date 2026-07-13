<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\DepsPincoreActionCommand;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'deps:update',
    description: 'Update Composer and npm dependencies across the project',
)]
final class DepsUpdateCommand extends DepsPincoreActionCommand
{
    public function __construct()
    {
        parent::__construct(
            name: 'deps:update',
            description: 'Update Composer and npm dependencies across the project',
            defaultArgv: [],
            forwardOptionNames: [],
            help: 'Example: pinx deps:update com_my_shop',
        );
    }

    protected function configureOptions(): void
    {
        $this->configureDepsInstallUpdateOptions();
    }

    protected function depsAction(): string
    {
        return 'update';
    }

    /**
     * @return list<string>
     */
    protected function depsForwardOptionNames(): array
    {
        return self::depsForwardOptionNames();
    }
}
