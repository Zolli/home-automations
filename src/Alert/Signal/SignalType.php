<?php

declare(strict_types=1);

namespace Shared\Alert\Signal;

use Shared\Alert\Config\InvalidAlertConfig;
use Shared\Alert\Config\TypedOptions;

interface SignalType
{
    public const string KIND = 'Signal';

    public function getName(): string;

    /** @throws InvalidAlertConfig */
    public function mapToSignal(TypedOptions $options): Signal;
}
