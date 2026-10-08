<?php

declare(strict_types=1);

namespace Shared\Alert\Rule;

enum ThresholdDirection: string
{
    case Above = 'above';
    case Below = 'below';

    public function isBeyond(float $value, float $threshold): bool
    {
        return match ($this) {
            self::Above => $value > $threshold,
            self::Below => $value < $threshold,
        };
    }

    public function hasReturned(float $value, float $threshold, float $hysteresis): bool
    {
        return match ($this) {
            self::Above => $value <= $threshold - $hysteresis,
            self::Below => $value >= $threshold + $hysteresis,
        };
    }
}
