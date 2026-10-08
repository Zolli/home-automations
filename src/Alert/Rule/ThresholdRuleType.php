<?php

declare(strict_types=1);

namespace Shared\Alert\Rule;

use Shared\Alert\Config\TypedOptions;

final readonly class ThresholdRuleType implements AlertRuleType
{
    public function __construct(private ThresholdDirection $direction) {}

    public function getName(): string
    {
        return $this->direction->value;
    }

    public function mapToRule(TypedOptions $options): AlertRule
    {
        return new ThresholdRule(
            $options->requireSources(),
            $this->direction,
            $options->requireNumber('threshold'),
            $options->findHysteresis(),
            $options->findHoldDuration(),
            $options->findString('attribute'),
            $options->findBool('unavailableIsFault', false),
        );
    }
}
