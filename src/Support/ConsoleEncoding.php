<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

final class ConsoleEncoding
{
    public static function bootUtf8(): void
    {
        if (PHP_SAPI !== 'cli') {
            return;
        }

        ini_set('default_charset', 'UTF-8');

        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding('UTF-8');
        }

        if (PHP_OS_FAMILY === 'Windows' && function_exists('sapi_windows_cp_set')) {
            @sapi_windows_cp_set(65001);
        }
    }
}
