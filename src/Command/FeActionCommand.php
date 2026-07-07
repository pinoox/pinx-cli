<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\AppContext;
use Pinoox\PinxCli\Support\FeForwardOptions;
use Pinoox\PinxCli\Support\PincoreActionCommand;
use Symfony\Component\Console\Input\InputInterface;

abstract class FeActionCommand extends PincoreActionCommand
{
    public function __construct(
        string $name,
        string $description,
        private readonly string $action,
        array $aliases = [],
        ?string $help = null,
        ?array $forwardOptionNames = null,
    ) {
        parent::__construct(
            name: $name,
            description: $description,
            defaultArgv: [],
            forwardOptionNames: $forwardOptionNames ?? FeForwardOptions::basicForwardNames(),
            aliases: $aliases,
            help: $help,
        );
    }

    protected function configureOptions(): void
    {
        $this->addForwardOptions([
            ...FeForwardOptions::theme(),
            ...FeForwardOptions::scaffold(),
            ...FeForwardOptions::runScript(),
            ...FeForwardOptions::install(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    protected function extraEnv(AppContext $context, InputInterface $input): array
    {
        if (in_array($this->action, ['dev', 'dev:apps'], true)) {
            return ['PINOOX_VITE_HMR' => '1'];
        }

        return [];
    }

    /**
     * @return list<string>
     */
    protected function pincoreArgv(AppContext $context, InputInterface $input): array
    {
        return ['fe', $context->package, $this->action];
    }
}
