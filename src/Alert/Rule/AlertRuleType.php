<?php

declare(strict_types=1);

namespace Shared\Alert\Rule;

use Shared\Config\InvalidTypedOptions;
use Shared\Config\NamedType;
use Shared\Config\TypedOptions;

interface AlertRuleType extends NamedType
{
    public const string KIND = 'Alert rule';

    public const string HOLD_KEY = 'forSeconds';

    /** @throws InvalidTypedOptions */
    public function mapToRule(TypedOptions $options): AlertRule;
}
