<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Command;

use Pinoox\PinxCli\Support\ForwardsPincoreCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'provision',
    description: 'Install Pinoox on a blank host via Pinroll (php pinoox pinroll:provision)',
    aliases: ['pinroll:provision'],
)]
final class ProvisionCommand extends Command
{
    use ForwardsPincoreCommand;

    protected function configure(): void
    {
        $this
            ->addArgument('host', InputArgument::OPTIONAL, 'Pinroll host name')
            ->addOption('via', null, InputOption::VALUE_REQUIRED, 'Transport: ftp or ssh')
            ->addOption('setup-only', null, InputOption::VALUE_NONE, 'Skip zip upload/extract; only run database + admin setup')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing files / re-run setup')
            ->addOption('reupload', null, InputOption::VALUE_NONE, 'Rebuild and re-upload platform.zip')
            ->addOption('lang', null, InputOption::VALUE_REQUIRED, 'Installer language: en or fa')
            ->addOption('db-host', null, InputOption::VALUE_REQUIRED, 'Database host')
            ->addOption('db-database', null, InputOption::VALUE_REQUIRED, 'Database name')
            ->addOption('db-username', null, InputOption::VALUE_REQUIRED, 'Database username')
            ->addOption('db-password', null, InputOption::VALUE_REQUIRED, 'Database password')
            ->addOption('db-connection', null, InputOption::VALUE_REQUIRED, 'mysql, mariadb, pgsql, or sqlsrv')
            ->addOption('db-port', null, InputOption::VALUE_REQUIRED, 'Database port')
            ->addOption('db-prefix', null, InputOption::VALUE_REQUIRED, 'Table prefix')
            ->addOption('db-timezone', null, InputOption::VALUE_REQUIRED, 'Database timezone')
            ->addOption('admin-fname', null, InputOption::VALUE_REQUIRED, 'Admin first name')
            ->addOption('admin-lname', null, InputOption::VALUE_REQUIRED, 'Admin last name')
            ->addOption('admin-email', null, InputOption::VALUE_REQUIRED, 'Admin email')
            ->addOption('admin-username', null, InputOption::VALUE_REQUIRED, 'Admin username')
            ->addOption('admin-password', null, InputOption::VALUE_REQUIRED, 'Admin password')
            ->setHelp('Requires pinoox/pinroll. Example: pinx provision');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $context = $this->requireApp($io);
        if ($context === null) {
            return Command::FAILURE;
        }

        $pinroll = $context->root . '/vendor/pinoox/pinroll/src/Pinroll.php';
        if (!is_file($pinroll) && !class_exists(\Pinoox\Pinroll\Pinroll::class, false)) {
            $io->error('Pinroll is not installed. Run: composer require --dev pinoox/pinroll');

            return Command::FAILURE;
        }

        return $this->forwardPincoreCommand(
            $io,
            $input,
            $output,
            'pinroll:provision',
            [
                'via', 'setup-only', 'force', 'reupload', 'lang',
                'db-host', 'db-database', 'db-username', 'db-password',
                'db-connection', 'db-port', 'db-prefix', 'db-timezone',
                'admin-fname', 'admin-lname', 'admin-email', 'admin-username', 'admin-password',
            ],
            ['host'],
            false,
        );
    }
}
