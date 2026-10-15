<?php

declare(strict_types=1);

namespace Shared\HouseMode\Trigger;

use Shared\Config\InvalidTypedOptions;
use Shared\Config\NamedType;
use Shared\Config\TypedOptions;

interface ModeTriggerType extends NamedType
{
    public const string KIND     = 'Mode strategy';
    public const string HOLD_KEY = 'forSeconds';

    /** @throws InvalidTypedOptions */
    public function mapToTrigger(string $name, TypedOptions $options): ModeTrigger;
}
