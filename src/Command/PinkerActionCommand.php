<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\AppContext;
use Pinoox\PinxCli\Support\PincoreActionCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

abstract class PinkerActionCommand extends PincoreActionCommand
{
    public function __construct(
        string $name,
        string $description,
        private readonly string $action,
        ?string $help = null,
    ) {
        parent::__construct(
            name: $name,
            description: $description,
            defaultArgv: [],
            forwardOptionNames: ['force', 'plain'],
            help: $help,
        );
    }

    protected function configureOptions(): void
    {
        $this->addForwardOptions([
            ['force', 'f', InputOption::VALUE_NONE, 'Force rebuild or clear'],
            ['plain', null, InputOption::VALUE_NONE, 'Plain output'],
        ]);
    }

    /**
     * @return list<string>
     */
    protected function pincoreArgv(AppContext $context, InputInterface $input): array
    {
        return ['pinker:' . $this->action, $context->package];
    }
}
