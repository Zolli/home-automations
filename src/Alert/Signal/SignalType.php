<?php

declare(strict_types=1);

namespace Shared\Alert\Signal;

use Shared\Config\InvalidTypedOptions;
use Shared\Config\NamedType;
use Shared\Config\TypedOptions;

interface SignalType extends NamedType
{
    public const string KIND = 'Signal';

    /** @throws InvalidTypedOptions */
    public function mapToSignal(TypedOptions $options): Signal;
}
