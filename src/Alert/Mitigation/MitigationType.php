<?php

declare(strict_types=1);

namespace Shared\Alert\Mitigation;

use Shared\Config\InvalidTypedOptions;
use Shared\Config\NamedType;
use Shared\Config\TypedOptions;

interface MitigationType extends NamedType
{
    public const string KIND = 'Mitigation';

    /** @throws InvalidTypedOptions */
    public function mapToMitigation(TypedOptions $options): Mitigation;
}
