<?php

declare(strict_types=1);

namespace Shared\HouseMode\Strategy;

use Shared\Config\InvalidTypedOptions;
use Shared\Config\TypedOptions;
use Shared\Threshold\ThresholdDirection;

final readonly class ThresholdStrategyType implements ModeStrategyType
{
    public function __construct(
        private ThresholdDirection $direction,
    ) {}

    public function getName(): string
    {
        return $this->direction->value;
    }

    public function mapToStrategy(string $name, TypedOptions $options): ModeStrategy
    {
        $range     = $this->mapRange($options);
        $threshold = $options->requireNumber('threshold');

        if (!$range->includes($threshold)) {
            throw InvalidTypedOptions::forInvalidOption(
                $options->kind,
                $options->type,
                'threshold',
                'a number within min and max',
            );
        }

        return new ThresholdStrategy(
            $name,
            $options->requireEntityId('entity'),
            $this->direction,
            $threshold,
            $range,
            $options->findNonNegativeNumber('hysteresis'),
            $options->findPositiveDuration(self::HOLD_KEY),
        );
    }

    /** @throws InvalidTypedOptions */
    private function mapRange(TypedOptions $options): ThresholdRange
    {
        $min  = $options->hasOption('min') ? $options->requireNumber('min') : 0.0;
        $max  = $options->requireNumber('max');
        $step = $options->hasOption('step') ? $options->requireNumber('step') : 1.0;

        if ($max <= $min) {
            throw InvalidTypedOptions::forInvalidOption($options->kind, $options->type, 'max', 'a number above min');
        }

        if ($step <= 0.0) {
            throw InvalidTypedOptions::forInvalidOption($options->kind, $options->type, 'step', 'a positive number');
        }

        return new ThresholdRange($min, $max, $step, $options->findString('unit'));
    }
}
