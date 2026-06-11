<?php

declare(strict_types=1);

namespace Pinoox\PinxCli;

use Pinoox\PinxCli\Command\ApiDocsCommand;
use Pinoox\PinxCli\Command\BuildCommand;
use Pinoox\PinxCli\Command\DepsCommand;
use Pinoox\PinxCli\Command\DevCommand;
use Pinoox\PinxCli\Command\DoctorCommand;
use Pinoox\PinxCli\Command\FrontendCommand;
use Pinoox\PinxCli\Command\GraphQLDocsCommand;
use Pinoox\PinxCli\Command\InfoCommand;
use Pinoox\PinxCli\Command\InitCommand;
use Pinoox\PinxCli\Command\MakeCommand;
use Pinoox\PinxCli\Command\MigrateCommand;
use Pinoox\PinxCli\Command\NewCommand;
use Pinoox\PinxCli\Command\PatchRollbackCommand;
use Pinoox\PinxCli\Command\PatchRunCommand;
use Pinoox\PinxCli\Command\PatchStatusCommand;
use Pinoox\PinxCli\Command\PinkerCommand;
use Pinoox\PinxCli\Command\ReleaseCommand;
use Pinoox\PinxCli\Command\RouteActionsCommand;
use Pinoox\PinxCli\Command\ScheduleListCommand;
use Pinoox\PinxCli\Command\ScheduleRunCommand;
use Pinoox\PinxCli\Command\SeederRunCommand;
use Pinoox\PinxCli\Command\SetupCommand;
use Pinoox\PinxCli\Command\TestCommand;
use Pinoox\PinxCli\Command\VersionCommand;
use Pinoox\PinxCli\Support\PinxVersion;
use Symfony\Component\Console\Application;

final class ConsoleApplication extends Application
{
    public function __construct()
    {
        parent::__construct('pinx', PinxVersion::VERSION);

        $this->addCommands([
            new NewCommand(),
            new InitCommand(),
            new SetupCommand(),
            new DevCommand(),
            new MigrateCommand(),
            new BuildCommand(),
            new DoctorCommand(),
            new ReleaseCommand(),
            new InfoCommand(),
            new MakeCommand(),
            new RouteActionsCommand(),
            new DepsCommand(),
            new FrontendCommand(),
            new ScheduleListCommand(),
            new ScheduleRunCommand(),
            new SeederRunCommand(),
            new PatchRunCommand(),
            new PatchStatusCommand(),
            new PatchRollbackCommand(),
            new PinkerCommand(),
            new ApiDocsCommand(),
            new GraphQLDocsCommand(),
            new TestCommand(),
            new VersionCommand(),
        ]);
    }
}
