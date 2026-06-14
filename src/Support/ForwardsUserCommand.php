<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

trait ForwardsUserCommand
{
    use RunsForApp;

    /**
     * @param list<string> $optionNames
     */
    protected function forwardUserCommand(
        SymfonyStyle $io,
        InputInterface $input,
        OutputInterface $output,
        string $pincoreCommand,
        array $optionNames = [],
        ?string $user = null,
    ): int {
        $context = $this->requireApp($io);

        if ($context === null) {
            return Command::FAILURE;
        }

        $args = [$pincoreCommand];

        if ($user !== null && $user !== '') {
            $args[] = $user;
        } elseif ($input->hasArgument('user')) {
            $userArg = $input->getArgument('user');
            if (is_string($userArg) && $userArg !== '') {
                $args[] = $userArg;
            }
        }

        $args = array_merge($args, $this->forwardOptions($input, $optionNames));

        return $this->runPincore($context, $args, $output);
    }
}
