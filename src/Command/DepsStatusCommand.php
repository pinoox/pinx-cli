<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\AppContext;
use Pinoox\PinxCli\Support\PincoreActionCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'deps:status',
    description: 'Show Composer and npm dependency status for the app',
)]
final class DepsStatusCommand extends PincoreActionCommand
{
    public function __construct()
    {
        parent::__construct(
            name: 'deps:status',
            description: 'Show Composer and npm dependency status for the app',
            defaultArgv: [],
            forwardOptionNames: ['composer-only', 'npm-only', 'theme', 'all-themes'],
            help: 'Example: pinx deps:status --all-themes',
        );
    }

    protected function configureOptions(): void
    {
        $this->addForwardOptions([
            ['composer-only', null, InputOption::VALUE_NONE, 'Only Composer targets'],
            ['npm-only', null, InputOption::VALUE_NONE, 'Only npm targets'],
            ['theme', null, InputOption::VALUE_REQUIRED, 'Theme folder, context (site, panel, …), or all'],
            ['all-themes', null, InputOption::VALUE_NONE, 'Every theme context or folder with package.json'],
        ]);
    }

    /**
     * @return list<string>
     */
    protected function pincoreArgv(AppContext $context, InputInterface $input): array
    {
        return ['deps', 'status', $context->package];
    }
}
