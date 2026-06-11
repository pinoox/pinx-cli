<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\CommandCatalog;
use Pinoox\PinxCli\Support\PinxVersion;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\ListCommand as SymfonyListCommand;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Descriptor\ApplicationDescription;
use Symfony\Component\Console\Helper\DescriptorHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputDefinition;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'list',
    description: 'List commands grouped by area',
)]
final class ListCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setDefinition(new InputDefinition([
                new InputArgument('namespace', InputArgument::OPTIONAL, 'Filter by command prefix', null, static function () {
                    return [];
                }),
                new InputOption('raw', null, InputOption::VALUE_NONE, 'List commands as plain lines'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (txt, json, xml, md)', 'txt'),
                new InputOption('short', null, InputOption::VALUE_NONE, 'Omit command descriptions'),
                new InputOption('ignore-hidden', null, InputOption::VALUE_NONE, 'Include hidden commands'),
            ]))
            ->setHelp(
                <<<'HELP'
The list command displays grouped pinx commands for single-app projects.

Examples:
  pinx list
  pinx list migrate
  pinx list --raw
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $application = $this->getApplication();

        if ($application === null) {
            return Command::FAILURE;
        }

        $namespace = (string) ($input->getArgument('namespace') ?? '');
        $format = (string) $input->getOption('format');

        if ($input->getOption('raw') || $format !== 'txt') {
            $fallback = new SymfonyListCommand();
            $fallback->setApplication($application);

            return $fallback->run($input, $output);
        }

        $description = new ApplicationDescription(
            $application,
            $namespace !== '' ? $namespace : null,
            !(bool) $input->getOption('ignore-hidden'),
        );

        $commands = $description->getCommands();

        if ($namespace !== '') {
            $helper = new DescriptorHelper();
            $helper->describe($output, $application, [
                'format' => $format,
                'raw_text' => false,
                'namespace' => $namespace,
                'short' => (bool) $input->getOption('short'),
            ]);

            return Command::SUCCESS;
        }

        $io = new SymfonyStyle($input, $output);
        $io->title('pinx ' . PinxVersion::version() . ' — single-app commands');

        $bucketed = [];

        foreach ($commands as $name => $command) {
            if ($command->getName() === null || $command->isHidden()) {
                continue;
            }

            $section = CommandCatalog::sectionFor($name);
            $bucketed[$section][$name] = $command;
        }

        foreach (CommandCatalog::sections() as $section) {
            $items = $bucketed[$section['key']] ?? [];

            if ($items === []) {
                continue;
            }

            ksort($items);
            $io->section($section['label']);
            $io->text('<comment>' . $section['description'] . '</comment>');

            $rows = [];

            foreach ($items as $name => $command) {
                $aliases = $command->getAliases();

                if ($aliases !== []) {
                    $name .= ' <fg=gray>(' . implode(', ', $aliases) . ')</>';
                }

                $rows[] = [
                    $name,
                    (bool) $input->getOption('short') ? '' : ($command->getDescription() ?? ''),
                ];
            }

            $io->table(['Command', 'Description'], $rows);
        }

        $io->writeln('Use <info>pinx help &lt;command&gt;</info> for details.');

        return Command::SUCCESS;
    }

    public function complete(CompletionInput $input, \Symfony\Component\Console\Completion\CompletionSuggestions $suggestions): void
    {
        $fallback = new SymfonyListCommand();
        $fallback->setApplication($this->getApplication());
        $fallback->complete($input, $suggestions);
    }
}
