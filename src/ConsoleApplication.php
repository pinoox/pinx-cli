<?php

declare(strict_types=1);

namespace Pinoox\PinxCli;

use Pinoox\PinxCli\Command\ApiDocsCommand;
use Pinoox\PinxCli\Command\BuildCommand;
use Pinoox\PinxCli\Command\DepsCommand;
use Pinoox\PinxCli\Command\DepsInstallCommand;
use Pinoox\PinxCli\Command\DepsStatusCommand;
use Pinoox\PinxCli\Command\DepsUpdateCommand;
use Pinoox\PinxCli\Command\DevCommand;
use Pinoox\PinxCli\Command\DoctorCommand;
use Pinoox\PinxCli\Command\FeBuildCommand;
use Pinoox\PinxCli\Command\FeDevCommand;
use Pinoox\PinxCli\Command\FeInfoCommand;
use Pinoox\PinxCli\Command\FeInstallCommand;
use Pinoox\PinxCli\Command\FeScaffoldCommand;
use Pinoox\PinxCli\Command\FrontendCommand;
use Pinoox\PinxCli\Command\GraphQLDocsCommand;
use Pinoox\PinxCli\Command\InfoCommand;
use Pinoox\PinxCli\Command\InitCommand;
use Pinoox\PinxCli\Command\ListCommand;
use Pinoox\PinxCli\Command\MakeCommand;
use Pinoox\PinxCli\Command\MigrateCommand;
use Pinoox\PinxCli\Command\MigrateCreateCommand;
use Pinoox\PinxCli\Command\MigratePlatformCommand;
use Pinoox\PinxCli\Command\MigrateRollbackCommand;
use Pinoox\PinxCli\Command\MigrateStatusCommand;
use Pinoox\PinxCli\Command\NewCommand;
use Pinoox\PinxCli\Command\PatchRollbackCommand;
use Pinoox\PinxCli\Command\PatchRunCommand;
use Pinoox\PinxCli\Command\PatchStatusCommand;
use Pinoox\PinxCli\Command\PinkerClearCommand;
use Pinoox\PinxCli\Command\PinkerCommand;
use Pinoox\PinxCli\Command\PinkerDiffCommand;
use Pinoox\PinxCli\Command\PinkerOverridesCommand;
use Pinoox\PinxCli\Command\PinkerRebuildCommand;
use Pinoox\PinxCli\Command\PinkerStatusCommand;
use Pinoox\PinxCli\Command\ReleaseCommand;
use Pinoox\PinxCli\Command\RouteActionsCommand;
use Pinoox\PinxCli\Command\ScheduleListCommand;
use Pinoox\PinxCli\Command\ScheduleRunCommand;
use Pinoox\PinxCli\Command\SeederRunCommand;
use Pinoox\PinxCli\Command\SelfUpdateCommand;
use Pinoox\PinxCli\Command\SetupCommand;
use Pinoox\PinxCli\Command\TestCommand;
use Pinoox\PinxCli\Command\VersionCommand;
use Pinoox\PinxCli\Support\CommandCatalog;
use Pinoox\PinxCli\Support\PinxVersion;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;

final class ConsoleApplication extends Application
{
    public function __construct()
    {
        parent::__construct('pinx', PinxVersion::version());

        foreach ([
            // Project
            new NewCommand(),
            new InitCommand(),
            new SetupCommand(),
            new DoctorCommand(),
            new InfoCommand(),

            // Development
            new DevCommand(),

            // Database
            new MigrateCommand(),
            new MigrateRollbackCommand(),
            new MigrateStatusCommand(),
            new MigrateCreateCommand(),
            new MigratePlatformCommand(),
            new SeederRunCommand(),
            new PatchRunCommand(),
            new PatchStatusCommand(),
            new PatchRollbackCommand(),

            // Build & release
            new BuildCommand(),
            new ReleaseCommand(),

            // Scaffolding
            new MakeCommand(),

            // Routes
            new RouteActionsCommand(),

            // Dependencies
            new DepsCommand(),
            new DepsStatusCommand(),
            new DepsInstallCommand(),
            new DepsUpdateCommand(),

            // Frontend
            new FrontendCommand(),
            new FeInfoCommand(),
            new FeInstallCommand(),
            new FeBuildCommand(),
            new FeDevCommand(),
            new FeScaffoldCommand(),

            // Schedule
            new ScheduleListCommand(),
            new ScheduleRunCommand(),

            // Pinker
            new PinkerCommand(),
            new PinkerStatusCommand(),
            new PinkerRebuildCommand(),
            new PinkerDiffCommand(),
            new PinkerClearCommand(),
            new PinkerOverridesCommand(),

            // Quality & docs
            new TestCommand(),
            new ApiDocsCommand(),
            new GraphQLDocsCommand(),

            // Meta
            new VersionCommand(),
            new SelfUpdateCommand(),
            new ListCommand(),
        ] as $command) {
            $this->registerCommand($command);
        }
    }

    private function registerCommand(Command $command): void
    {
        $name = $command->getName();

        if ($name !== null) {
            $aliases = CommandCatalog::aliasesFor($name);

            if ($aliases !== []) {
                $command->setAliases(array_values(array_unique([
                    ...$command->getAliases(),
                    ...$aliases,
                ])));
            }
        }

        $this->add($command);
    }
}
