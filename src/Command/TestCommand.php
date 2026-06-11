<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\RunsForApp;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'test',
    description: 'Run Pest/PHPUnit tests for the app',
    aliases: ['pest'],
)]
final class TestCommand extends Command
{
    use RunsForApp;

    protected function configure(): void
    {
        $this
            ->addOption('filter', 'f', InputOption::VALUE_REQUIRED, 'Run tests matching a name pattern')
            ->addOption('unit', 'u', InputOption::VALUE_NONE, 'Run only unit tests')
            ->addOption('feature', null, InputOption::VALUE_NONE, 'Run only feature tests')
            ->addOption('group', 'g', InputOption::VALUE_REQUIRED, 'Run tests in a @group')
            ->addOption('exclude-group', null, InputOption::VALUE_REQUIRED, 'Skip tests in a @group')
            ->addOption('coverage', 'c', InputOption::VALUE_NONE, 'Generate code coverage report')
            ->setHelp('Examples: pinx test | pinx test --feature | pinx test --filter=Product');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = $this->requireApp($io);

        if ($context === null) {
            return Command::FAILURE;
        }

        $args = array_merge(
            ['test', $context->package],
            $this->forwardOptions($input, [
                'filter',
                'unit',
                'feature',
                'group',
                'exclude-group',
                'coverage',
            ]),
        );

        return $this->runPincore($context, $args, $output);
    }
}
