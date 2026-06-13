<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

final class PinxReleaseStatus
{
    public function __construct(
        public readonly string $current,
        public readonly ?string $latest,
        public readonly bool $updateAvailable,
        public readonly bool $checkSucceeded,
        public readonly bool $aheadOfRelease = false,
        public readonly ?string $error = null,
    ) {
    }

    public static function offline(string $current): self
    {
        return new self($current, null, false, false);
    }

    public static function failed(string $current, string $error): self
    {
        return new self($current, null, false, false, error: $error);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'current' => $this->current,
            'latest' => $this->latest,
            'update_available' => $this->updateAvailable,
            'ahead_of_release' => $this->aheadOfRelease,
            'check_succeeded' => $this->checkSucceeded,
            'error' => $this->error,
            'install' => PinxVersion::installLabel(),
            'install_mode' => PinxVersion::installMode(),
            'php' => PHP_VERSION,
        ];
    }
}
