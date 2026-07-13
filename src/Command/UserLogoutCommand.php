<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\ForwardsUserCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'user:logout',
    description: 'Clear PINOOX_LOGIN auto-login and end the auth session',
)]
final class UserLogoutCommand extends Command
{
    use ForwardsUserCommand;

    protected function configure(): void
    {
        $this
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Clear every PINOOX_LOGIN line')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output JSON')
            ->setHelp(
                <<<'HELP'
Clear PINOOX_LOGIN for the current app (or all):

  pinx user:logout
  pinx user:logout --all
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('all')) {
            $context = $this->requireApp($io);
            if ($context === null) {
                return Command::FAILURE;
            }

            return $this->runPincore($context, ['user:logout', '--all', ...$this->forwardOptions($input, ['json']), '-n'], $output);
        }

        return $this->forwardUserCommand(
            $io,
            $input,
            $output,
            'user:logout',
            ['json'],
        );
    }
}
