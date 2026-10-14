<?php

declare(strict_types=1);

namespace Shared\Condition;

use Shared\Config\InvalidTypedOptions;
use Shared\Config\NamedType;
use Shared\Config\TypedOptions;

interface ConditionType extends NamedType
{
    public const string KIND = 'Condition';

    /**
     * @throws InvalidTypedOptions
     * @throws InvalidCondition
     */
    public function mapToCondition(TypedOptions $options): Condition;
}
