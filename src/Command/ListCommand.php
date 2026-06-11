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
    private const NAME_WIDTH = 22;

    protected function configure(): void
    {
        $this
            ->setDefinition(new InputDefinition([
                new InputArgument('namespace', InputArgument::OPTIONAL, 'Filter by command prefix', null, static function () {
                    return [];
                }),
                new InputOption('raw', null, InputOption::VALUE_NONE, 'List commands as plain lines'),
                new InputOption('format', null, InputOption::VALUE_REQUIRED, 'Output format (txt, json, xml, md)', 'txt'),
                new InputOption('short', null, InputOption::VALUE_NONE, 'Hide command descriptions'),
                new InputOption('no-descriptions', null, InputOption::VALUE_NONE, 'Hide command descriptions'),
                new InputOption('ignore-hidden', null, InputOption::VALUE_NONE, 'Include hidden commands'),
            ]))
            ->setHelp(
                <<<'HELP'
The list command displays grouped pinx commands for single-app projects.

Examples:
  pinx list
  pinx list migrate
  pinx list --short
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
                'short' => !$this->showDescriptions($input),
            ]);

            return Command::SUCCESS;
        }

        $io = new SymfonyStyle($input, $output);
        $showDescriptions = $this->showDescriptions($input);
        $hidden = array_flip(CommandCatalog::hiddenFromList());
        $bucketed = [];

        foreach ($commands as $name => $command) {
            if ($command->getName() === null || $command->isHidden() || isset($hidden[$name])) {
                continue;
            }

            $section = CommandCatalog::sectionFor($name);
            $bucketed[$section][$name] = $command;
        }

        $io->writeln('');
        $io->writeln('<info>pinx ' . PinxVersion::version() . '</info>');

        foreach (CommandCatalog::sections() as $section) {
            $items = $bucketed[$section['key']] ?? [];

            if ($items === []) {
                continue;
            }

            ksort($items);
            $io->writeln('');
            $io->writeln('<comment>' . $section['label'] . '</>');

            foreach ($items as $name => $command) {
                $io->writeln($this->formatEntry($name, $command, $showDescriptions));
            }
        }

        $io->writeln('');
        $io->writeln('pinx help <info>command</info> for details.');
        $io->writeln('');

        return Command::SUCCESS;
    }

    private function showDescriptions(InputInterface $input): bool
    {
        return !$input->getOption('short') && !$input->getOption('no-descriptions');
    }

    private function formatEntry(string $name, Command $command, bool $showDescriptions): string
    {
        $aliases = CommandCatalog::displayAliasesFor($name, $command->getAliases());
        $aliasLabel = $this->aliasLabel($aliases);
        $nameCell = str_pad($name, self::NAME_WIDTH);
        $line = '  <info>' . $nameCell . '</>';

        if (!$showDescriptions) {
            return $aliasLabel !== '' ? $line . $aliasLabel : '  <info>' . $name . '</>';
        }

        $desc = $command->getDescription() ?? '';

        if ($aliasLabel === '') {
            return $line . ' ' . $desc;
        }

        return $line . $aliasLabel . ' ' . $desc;
    }

    /**
     * @param list<string> $aliases
     */
    private function aliasLabel(array $aliases): string
    {
        if ($aliases === []) {
            return '';
        }

        return ' <fg=gray>[' . implode(', ', $aliases) . ']</>';
    }

    public function complete(CompletionInput $input, \Symfony\Component\Console\Completion\CompletionSuggestions $suggestions): void
    {
        $fallback = new SymfonyListCommand();
        $fallback->setApplication($this->getApplication());
        $fallback->complete($input, $suggestions);
    }
}
