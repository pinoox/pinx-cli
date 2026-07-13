<?php

declare(strict_types=1);

namespace Pinoox\PinxCli;

use Pinoox\PinxCli\Command\ApiDocsCommand;
use Pinoox\PinxCli\Command\BuildCommand;
use Pinoox\PinxCli\Command\DbCreateCommand;
use Pinoox\PinxCli\Command\DbListCommand;
use Pinoox\PinxCli\Command\DbPrefixCommand;
use Pinoox\PinxCli\Command\DbShowCommand;
use Pinoox\PinxCli\Command\DbTestCommand;
use Pinoox\PinxCli\Command\DbUpdateCommand;
use Pinoox\PinxCli\Command\DepsCommand;
use Pinoox\PinxCli\Command\DepsInstallCommand;
use Pinoox\PinxCli\Command\DepsStatusCommand;
use Pinoox\PinxCli\Command\DepsUpdateCommand;
use Pinoox\PinxCli\Command\DevDbClearCommand;
use Pinoox\PinxCli\Command\DevDbExportCommand;
use Pinoox\PinxCli\Command\DevDbInspectCommand;
use Pinoox\PinxCli\Command\DevDbSeedCommand;
use Pinoox\PinxCli\Command\DevDbStatusCommand;
use Pinoox\PinxCli\Command\ServeCommand;
use Pinoox\PinxCli\Command\DevCommand;
use Pinoox\PinxCli\Command\DoctorCommand;
use Pinoox\PinxCli\Command\FileDeleteCommand;
use Pinoox\PinxCli\Command\FileListCommand;
use Pinoox\PinxCli\Command\FilePurgeCommand;
use Pinoox\PinxCli\Command\FileShowCommand;
use Pinoox\PinxCli\Command\FileUpdateCommand;
use Pinoox\PinxCli\Command\FeBuildCommand;
use Pinoox\PinxCli\Command\FeDevAppsCommand;
use Pinoox\PinxCli\Command\FeDevCommand;
use Pinoox\PinxCli\Command\FeInfoCommand;
use Pinoox\PinxCli\Command\FeInstallCommand;
use Pinoox\PinxCli\Command\FeScaffoldCommand;
use Pinoox\PinxCli\Command\FeWatchCommand;
use Pinoox\PinxCli\Command\ThemeCreateCommand;
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
use Pinoox\PinxCli\Command\PermissionCreateCommand;
use Pinoox\PinxCli\Command\PermissionDeleteCommand;
use Pinoox\PinxCli\Command\PermissionListCommand;
use Pinoox\PinxCli\Command\PermissionShowCommand;
use Pinoox\PinxCli\Command\PinionCleanCommand;
use Pinoox\PinxCli\Command\PinionInfoCommand;
use Pinoox\PinxCli\Command\PinionListCommand;
use Pinoox\PinxCli\Command\PinkerClearCommand;
use Pinoox\PinxCli\Command\PinkerCommand;
use Pinoox\PinxCli\Command\PinkerDiffCommand;
use Pinoox\PinxCli\Command\PinkerOverridesCommand;
use Pinoox\PinxCli\Command\PinkerRebuildCommand;
use Pinoox\PinxCli\Command\PinkerStatusCommand;
use Pinoox\PinxCli\Command\ReleaseCommand;
use Pinoox\PinxCli\Command\RepairCommand;
use Pinoox\PinxCli\Command\RoleCreateCommand;
use Pinoox\PinxCli\Command\RoleDeleteCommand;
use Pinoox\PinxCli\Command\RoleListCommand;
use Pinoox\PinxCli\Command\RolePermissionCommand;
use Pinoox\PinxCli\Command\RoleShowCommand;
use Pinoox\PinxCli\Command\RoleUpdateCommand;
use Pinoox\PinxCli\Command\RouteActionsCommand;
use Pinoox\PinxCli\Command\ScheduleListCommand;
use Pinoox\PinxCli\Command\ScheduleRunCommand;
use Pinoox\PinxCli\Command\SeederRunCommand;
use Pinoox\PinxCli\Command\SetupCommand;
use Pinoox\PinxCli\Command\InspectorCommand;
use Pinoox\PinxCli\Command\SyncCommand;
use Pinoox\PinxCli\Command\TestCommand;
use Pinoox\PinxCli\Command\TokenCreateCommand;
use Pinoox\PinxCli\Command\TokenDeleteCommand;
use Pinoox\PinxCli\Command\TokenListCommand;
use Pinoox\PinxCli\Command\TokenPurgeCommand;
use Pinoox\PinxCli\Command\TokenRevokeUserCommand;
use Pinoox\PinxCli\Command\TokenShowCommand;
use Pinoox\PinxCli\Command\TokenUpdateCommand;
use Pinoox\PinxCli\Command\UserCreateCommand;
use Pinoox\PinxCli\Command\UserDeleteCommand;
use Pinoox\PinxCli\Command\UserListCommand;
use Pinoox\PinxCli\Command\UserLoginCommand;
use Pinoox\PinxCli\Command\UserPasswordCommand;
use Pinoox\PinxCli\Command\UserRoleCommand;
use Pinoox\PinxCli\Command\UserShowCommand;
use Pinoox\PinxCli\Command\UserStatusCommand;
use Pinoox\PinxCli\Command\UserUpdateCommand;
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
        $this->setCatchExceptions(false);

        foreach ([
            // Project
            new NewCommand(),
            new InitCommand(),
            new SyncCommand(),
            new RepairCommand(),
            new SetupCommand(),
            new DoctorCommand(),
            new InfoCommand(),

            // Development
            new ServeCommand(),
            new DevCommand(),
            new InspectorCommand(),

            // Database
            new MigrateCommand(),
            new MigrateRollbackCommand(),
            new MigrateStatusCommand(),
            new MigrateCreateCommand(),
            new MigratePlatformCommand(),
            new SeederRunCommand(),
            new DbListCommand(),
            new DbShowCommand(),
            new DbTestCommand(),
            new DbCreateCommand(),
            new DbUpdateCommand(),
            new DbPrefixCommand(),
            new DevDbStatusCommand(),
            new DevDbClearCommand(),
            new DevDbExportCommand(),
            new DevDbInspectCommand(),
            new DevDbSeedCommand(),
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

            // Users
            new UserCreateCommand(),
            new UserLoginCommand(),
            new UserListCommand(),
            new UserShowCommand(),
            new UserUpdateCommand(),
            new UserStatusCommand(),
            new UserPasswordCommand(),
            new UserDeleteCommand(),
            new UserRoleCommand(),

            new RoleListCommand(),
            new RoleCreateCommand(),
            new RoleShowCommand(),
            new RoleUpdateCommand(),
            new RoleDeleteCommand(),
            new RolePermissionCommand(),

            new PermissionListCommand(),
            new PermissionCreateCommand(),
            new PermissionShowCommand(),
            new PermissionDeleteCommand(),

            new TokenListCommand(),
            new TokenShowCommand(),
            new TokenCreateCommand(),
            new TokenUpdateCommand(),
            new TokenDeleteCommand(),
            new TokenRevokeUserCommand(),
            new TokenPurgeCommand(),

            new FileListCommand(),
            new FileShowCommand(),
            new FileUpdateCommand(),
            new FileDeleteCommand(),
            new FilePurgeCommand(),

            new PinionListCommand(),
            new PinionInfoCommand(),
            new PinionCleanCommand(),

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
            new FeWatchCommand(),
            new FeDevAppsCommand(),
            new FeScaffoldCommand(),
            new ThemeCreateCommand(),

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
