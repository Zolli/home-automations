<?php

declare(strict_types=1);

namespace Shared\Alert\Rule;

use Shared\Alert\Config\TypedOptions;

final readonly class StateMatchRuleType implements AlertRuleType
{
    public function getName(): string
    {
        return 'state';
    }

    public function mapToRule(TypedOptions $options): AlertRule
    {
        return new StateMatchRule(
            $options->requireSources(),
            $options->findString('state') ?? 'on',
            $options->findHoldDuration(),
        );
    }
}
