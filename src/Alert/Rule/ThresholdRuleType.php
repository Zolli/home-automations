<?php

declare(strict_types=1);

namespace Shared\Alert\Rule;

use Shared\Alert\Config\ReadingSourceMapper;
use Shared\Config\TypedOptions;
use Shared\Threshold\ThresholdDirection;

final readonly class ThresholdRuleType implements AlertRuleType
{
    public function __construct(
        private ThresholdDirection $direction,
        private ReadingSourceMapper $sources,
    ) {}

    public function getName(): string
    {
        return $this->direction->value;
    }

    public function mapToRule(TypedOptions $options): AlertRule
    {
        return new ThresholdRule(
            $this->sources->mapToSources($options),
            $this->direction,
            $options->requireNumber('threshold'),
            $options->findNonNegativeNumber('hysteresis'),
            $options->findDuration(self::HOLD_KEY),
            $options->findString('attribute'),
            $options->findBool('unavailableIsFault', false),
        );
    }
}
