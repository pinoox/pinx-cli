<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\AppContext;
use Pinoox\PinxCli\Support\PincoreActionCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'patch:reset',
    description: 'Rollback all rollbackable patches (optionally clear history)',
)]
final class PatchResetCommand extends PincoreActionCommand
{
    public function __construct()
    {
        parent::__construct(
            name: 'patch:reset',
            description: 'Rollback all rollbackable patches (optionally clear history)',
            defaultArgv: [],
            forwardOptionNames: ['clear', 'force'],
            help: 'Examples: pinx patch:reset --force | pinx patch:reset --clear --force',
        );
    }

    protected function configureOptions(): void
    {
        $this
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Also delete all patch history records for the app')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Skip confirmation prompt');
    }

    /**
     * @return list<string>
     */
    protected function pincoreArgv(AppContext $context, InputInterface $input): array
    {
        return ['patch:reset', $context->package, '-n'];
    }
}
