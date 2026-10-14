<?php

declare(strict_types=1);

namespace App\Tests\Shared\HouseMode;

use Shared\HouseMode\TransitionFiring;

final class FiringRecorder
{
    /** @var list<string> */
    public array $firedStrategies = [];

    public function recordStrategyFiring(string $strategyName): void
    {
        $this->firedStrategies[] = $strategyName;
    }

    public function recordTransitionFiring(TransitionFiring $firing): void
    {
        $this->firedStrategies[] = "{$firing->transitionName}:{$firing->strategyName}";
    }
}
