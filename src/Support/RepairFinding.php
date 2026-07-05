<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

final class RepairFinding
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $detail,
        public readonly bool $fixable = true,
    ) {
    }
}
