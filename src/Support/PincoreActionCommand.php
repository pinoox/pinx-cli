<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Thin pinx wrapper that forwards a fixed pincore argv prefix for the current app.
 */
abstract class PincoreActionCommand extends Command
{
    use RunsForApp;

    /**
     * @param list<string> $forwardOptions option names to pass through to pincore
     */
    public function __construct(
        string $name,
        string $description,
        private readonly array $defaultArgv,
        private readonly array $forwardOptionNames = [],
        array $aliases = [],
        ?string $help = null,
    ) {
        parent::__construct($name);
        $this->setDescription($description);

        if ($aliases !== []) {
            $this->setAliases($aliases);
        }

        if ($help !== null) {
            $this->setHelp($help);
        }
    }

    protected function configure(): void
    {
        $this->configureOptions();
    }

    protected function configureOptions(): void
    {
    }

    /**
     * @return list<string>
     */
    protected function pincoreArgv(AppContext $context, InputInterface $input): array
    {
        return $this->defaultArgv;
    }

    /**
     * @return array<string, string>
     */
    protected function extraEnv(AppContext $context, InputInterface $input): array
    {
        return [];
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = $this->requireApp($io);

        if ($context === null) {
            return Command::FAILURE;
        }

        $args = array_merge(
            $this->pincoreArgv($context, $input),
            $this->forwardOptions($input, $this->forwardOptionNames),
        );

        return $this->runPincore($context, $args, $output, $this->extraEnv($context, $input));
    }

    protected function addForwardOptions(array $definitions): void
    {
        foreach ($definitions as $definition) {
            if ($definition instanceof InputOption) {
                $this->addOption(
                    $definition->getName(),
                    $definition->getShortcut(),
                    $definition->getMode(),
                    $definition->getDescription(),
                    $definition->getDefault(),
                );

                continue;
            }

            if (!is_array($definition) || !isset($definition[0])) {
                continue;
            }

            $this->addOption(
                (string) $definition[0],
                $definition[1] ?? null,
                $definition[2] ?? InputOption::VALUE_NONE,
                $definition[3] ?? '',
                $definition[4] ?? null,
            );
        }
    }
}
