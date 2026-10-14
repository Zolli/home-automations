<?php

declare(strict_types=1);

namespace Shared\HouseMode\Trigger;

use Stewart\Contracts\Exception\ExposureException;
use Stewart\Contracts\Exposure\EntityCategory;
use Stewart\Contracts\Exposure\NumberConfig;
use Stewart\Contracts\Exposure\NumberMode;

final readonly class ThresholdRange
{
    public function __construct(
        public float $min,
        public float $max,
        public float $step = 1.0,
        public ?string $unit = null,
    ) {}

    public function includes(float $value): bool
    {
        return $value >= $this->min && $value <= $this->max;
    }

    /** @throws ExposureException */
    public function mapToNumberConfig(string $name): NumberConfig
    {
        return new NumberConfig(
            $this->min,
            $this->max,
            $this->step,
            NumberMode::Box,
            unit: $this->unit,
            name: $name,
            entityCategory: EntityCategory::Config,
        );
    }
}
