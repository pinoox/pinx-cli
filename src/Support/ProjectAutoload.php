<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

/**
 * Loads the target project's Composer autoload (and thus pincore helpers)
 * before requiring app.php from CLI context.
 *
 * pinx-cli often boots from its own vendor/ (global install or path repo),
 * so helpers like theme_flow_aliases() are missing until the project autoload runs.
 */
final class ProjectAutoload
{
    /** @var array<string, true> */
    private static array $booted = [];

    public static function boot(string $projectRoot): void
    {
        $projectRoot = ProjectRoot::normalize($projectRoot);

        if (isset(self::$booted[$projectRoot])) {
            return;
        }

        self::$booted[$projectRoot] = true;

        $autoload = $projectRoot . '/vendor/autoload.php';

        if (is_file($autoload)) {
            require_once $autoload;

            return;
        }

        $coreBase = CorePath::resolve($projectRoot) . '/functions/base.php';

        if (is_file($coreBase)) {
            require_once $coreBase;
        }
    }
}
