<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\AppContext;
use Pinoox\PinxCli\Support\PincoreActionCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

abstract class FeActionCommand extends PincoreActionCommand
{
    public function __construct(
        string $name,
        string $description,
        private readonly string $action,
        array $aliases = [],
        ?string $help = null,
    ) {
        parent::__construct(
            name: $name,
            description: $description,
            defaultArgv: [],
            forwardOptionNames: ['theme', 'script', 'stack', 'serve-host', 'serve-port', 'open', 'install'],
            aliases: $aliases,
            help: $help,
        );
    }

    protected function configureOptions(): void
    {
        $this->addForwardOptions([
            ['theme', 't', InputOption::VALUE_REQUIRED, 'Theme folder name'],
            ['script', null, InputOption::VALUE_REQUIRED, 'npm script name (run action)'],
            ['stack', null, InputOption::VALUE_REQUIRED, 'Stack for scaffold: vue, react, twig'],
            ['serve-host', null, InputOption::VALUE_REQUIRED, 'Dev server host'],
            ['serve-port', null, InputOption::VALUE_REQUIRED, 'Dev server port'],
            ['open', 'o', InputOption::VALUE_NONE, 'Open browser after start'],
            ['install', null, InputOption::VALUE_NONE, 'Force npm install'],
        ]);
    }

    /**
     * @return list<string>
     */
    protected function pincoreArgv(AppContext $context, InputInterface $input): array
    {
        return ['fe', $context->package, $this->action];
    }
}
