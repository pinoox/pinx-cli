<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\RunsForApp;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'patch:rollback',
    description: 'Rollback executed data patches for the app',
)]
final class PatchRollbackCommand extends Command
{
    use RunsForApp;

    protected function configure(): void
    {
        $this
            ->addArgument('patch', InputArgument::OPTIONAL, 'Patch name/class. Omit to rollback the latest rollbackable patch.')
            ->addOption('step', null, InputOption::VALUE_REQUIRED, 'Number of successful patches to rollback', '1')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Rollback every successful patch that supports down()')
            ->setHelp(
                <<<'HELP'
Examples:
  pinx patch:rollback
  pinx patch:rollback fix_user_roles
  pinx patch:rollback --step=2
  pinx patch:rollback --all
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = $this->requireApp($io);

        if ($context === null) {
            return Command::FAILURE;
        }

        $patch = trim((string) ($input->getArgument('patch') ?? ''));
        $args = ['patch:rollback'];

        if ($patch !== '') {
            $args[] = $patch;
        }

        $args[] = $context->package;
        $args = array_merge($args, $this->forwardOptions($input, ['step', 'all']));

        return $this->runPincore($context, $args, $output);
    }
}
