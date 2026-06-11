<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

final class PinxVersion
{
    public const VERSION = '1.0.0';

    public static function label(): string
    {
        return 'pinx ' . self::VERSION;
    }
}
