<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\AppContext;
use Pinoox\PinxCli\Support\PincoreActionCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

#[AsCommand(
    name: 'theme:create',
    description: 'Create a theme folder with theme.php and frontend stack stubs',
    aliases: ['theme:scaffold'],
)]
final class ThemeCreateCommand extends PincoreActionCommand
{
    public function __construct()
    {
        parent::__construct(
            name: 'theme:create',
            description: 'Create a theme folder with theme.php and frontend stack stubs',
            defaultArgv: [],
            forwardOptionNames: ['stack'],
            aliases: ['theme:scaffold'],
            help: 'Example: pinx theme:create panel --stack=vue',
        );
    }

    protected function configureOptions(): void
    {
        $this->addArgument('name', InputArgument::REQUIRED, 'Theme folder name');
        $this->addForwardOptions([
            ['stack', null, InputOption::VALUE_REQUIRED, 'Stack: twig, vite, vue, react'],
        ]);
    }

    /**
     * @return list<string>
     */
    protected function pincoreArgv(AppContext $context, InputInterface $input): array
    {
        return ['theme:create', (string) $input->getArgument('name'), $context->package];
    }
}
