<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\RunsForApp;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'patch:create',
    description: 'Create a data patch file for the current app',
)]
final class PatchCreateCommand extends Command
{
    use RunsForApp;

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Patch name (e.g. fix_user_roles)')
            ->setHelp('Example: pinx patch:create fix_user_roles');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = $this->requireApp($io);

        if ($context === null) {
            return Command::FAILURE;
        }

        $name = trim((string) $input->getArgument('name'));

        if ($name === '') {
            $io->error('Patch name is required.');

            return Command::INVALID;
        }

        return $this->runPincore($context, ['patch:create', $name, $context->package], $output);
    }
}
