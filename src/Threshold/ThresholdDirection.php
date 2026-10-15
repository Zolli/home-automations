<?php

declare(strict_types=1);

namespace Shared\Threshold;

use Stewart\Contracts\StateChangeStream;
use Stewart\Contracts\Time\Duration;

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

    public function filterCrossings(
        StateChangeStream $changes,
        float $threshold,
        ?Duration $holdDuration = null,
        float $hysteresis = 0.0,
    ): StateChangeStream {
        return match ($this) {
            self::Above => $changes->whenAbove($threshold, $holdDuration, hysteresis: $hysteresis),
            self::Below => $changes->whenBelow($threshold, $holdDuration, hysteresis: $hysteresis),
        };
    }
}
