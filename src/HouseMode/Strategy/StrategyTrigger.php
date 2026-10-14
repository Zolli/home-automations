<?php

declare(strict_types=1);

namespace Shared\HouseMode\Strategy;

use Closure;

interface StrategyTrigger
{
    public function getStrategyName(): string;

    /** @param Closure(string): void $whenFired */
    public function arm(Closure $whenFired): void;

    public function disarm(): void;

    public function detach(): void;

    public function describe(): string;
}
