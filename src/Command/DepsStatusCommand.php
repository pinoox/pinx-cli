<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\DepsPincoreActionCommand;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(
    name: 'deps:status',
    description: 'Show Composer and npm dependency status across the project',
)]
final class DepsStatusCommand extends DepsPincoreActionCommand
{
    public function __construct()
    {
        parent::__construct(
            name: 'deps:status',
            description: 'Show Composer and npm dependency status across the project',
            defaultArgv: [],
            forwardOptionNames: [],
            help: 'Example: pinx deps:status all',
        );
    }

    protected function configureOptions(): void
    {
        $this->configureDepsStatusOptions();
    }

    protected function depsAction(): string
    {
        return 'status';
    }

    /**
     * @return list<string>
     */
    protected function depsForwardOptionNames(): array
    {
        return self::depsStatusForwardOptionNames();
    }
}
