<?php

declare(strict_types=1);

namespace Pinoox\PinxCli\Support;

final class WizardCancelledException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Wizard cancelled.');
    }
}
