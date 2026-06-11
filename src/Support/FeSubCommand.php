<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

abstract class FeSubCommand extends PincoreActionCommand
{
    public function __construct(
        string $name,
        string $description,
        private readonly string $action,
        array $forwardOptionNames = [],
        ?string $help = null,
    ) {
        parent::__construct($name, $description, [], $forwardOptionNames, [], $help);
    }

    /**
     * @return list<string>
     */
    protected function pincoreArgv(AppContext $context, InputInterface $input): array
    {
        return ['fe', $context->package, $this->action];
    }

    protected function configureThemeOptions(): void
    {
        $this->addForwardOptions([
            ['theme', 't', InputOption::VALUE_REQUIRED, 'Theme folder name'],
        ]);
    }

    protected function configureDevOptions(): void
    {
        $this->configureThemeOptions();
        $this->addForwardOptions([
            ['serve-host', null, InputOption::VALUE_REQUIRED, 'Dev server host'],
            ['serve-port', null, InputOption::VALUE_REQUIRED, 'Dev server port'],
            ['open', 'o', InputOption::VALUE_NONE, 'Open browser after start'],
            ['install', null, InputOption::VALUE_NONE, 'Force npm install'],
        ]);
    }
}
