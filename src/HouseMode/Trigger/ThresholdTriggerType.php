<?php

declare(strict_types=1);

namespace Shared\HouseMode\Trigger;

use Shared\Config\InvalidTypedOptions;
use Shared\Config\TypedOptions;
use Shared\Threshold\ThresholdDirection;

final readonly class ThresholdTriggerType implements ModeTriggerType
{
    public function __construct(
        private ThresholdDirection $direction,
    ) {}

    public function getName(): string
    {
        return $this->direction->value;
    }

    public function mapToTrigger(string $name, TypedOptions $options): ModeTrigger
    {
        $min       = $options->hasOption('min') ? $options->requireNumber('min') : 0.0;
        $max       = $options->requireNumber('max');
        $step      = $options->hasOption('step') ? $options->requireNumber('step') : 1.0;
        $threshold = $options->requireNumber('threshold');
        $range     = new ThresholdRange($min, $max, $step, $options->findString('unit'));

        if ($max <= $min) {
            throw $this->createInvalidOption($options, 'max', 'a number above min');
        }

        if ($step <= 0.0) {
            throw $this->createInvalidOption($options, 'step', 'a positive number');
        }

        if (!$range->includes($threshold)) {
            throw $this->createInvalidOption($options, 'threshold', 'a number within min and max');
        }

        return new ThresholdTrigger(
            $name,
            $options->requireEntityId('entity'),
            $this->direction,
            $threshold,
            $range,
            $options->findNonNegativeNumber('hysteresis'),
            $options->findPositiveDuration(self::HOLD_KEY),
        );
    }

    private function createInvalidOption(TypedOptions $options, string $key, string $expectation): InvalidTypedOptions
    {
        return InvalidTypedOptions::forInvalidOption($options->kind, $options->type, $key, $expectation);
    }
}
