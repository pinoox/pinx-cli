<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * pinx deps:* → pincore deps {action} [package] with optional scope selection.
 */
abstract class DepsPincoreActionCommand extends PincoreActionCommand
{
    use DepsForward;

    protected function configure(): void
    {
        $this->configureDepsPackageArgument();
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (($code = $this->validateDepsOptions($input, $io)) !== null) {
            return $code;
        }

        return parent::execute($input, $output);
    }

    /**
     * @return list<string>
     */
    protected function pincoreArgv(AppContext $context, InputInterface $input): array
    {
        return $this->buildDepsArgv($this->depsAction(), $input, $this->depsForwardOptionNames());
    }

    abstract protected function depsAction(): string;

    /**
     * @return list<string>
     */
    abstract protected function depsForwardOptionNames(): array;
}
