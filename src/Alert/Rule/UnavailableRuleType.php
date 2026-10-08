<?php

declare(strict_types=1);

namespace Shared\Alert\Rule;

use Shared\Alert\Config\TypedOptions;

final readonly class UnavailableRuleType implements AlertRuleType
{
    public function getName(): string
    {
        return 'unavailable';
    }

    public function mapToRule(TypedOptions $options): AlertRule
    {
        return new UnavailableRule($options->requireSources(), $options->findHoldDuration());
    }
}
