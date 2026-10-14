<?php

declare(strict_types=1);

namespace Shared\HouseMode\Strategy;

use Closure;
use Stewart\Contracts\HaContext;
use Stewart\Contracts\Subscription;

final class StateTrigger implements StrategyTrigger
{
    private ?Subscription $stateChanges = null;

    public function __construct(
        private readonly StateStrategy $strategy,
        private readonly HaContext $ha,
    ) {}

    public function getStrategyName(): string
    {
        return $this->strategy->name;
    }

    public function arm(Closure $whenFired): void
    {
        $this->disarm();
        $this->stateChanges = $this->ha
            ->watchStateChanges($this->strategy->entityId)
            ->whenChangedTo($this->strategy->state, $this->strategy->holdDuration)
            ->subscribe(fn() => $whenFired($this->strategy->name));
    }

    public function disarm(): void
    {
        $this->stateChanges?->unsubscribe();
        $this->stateChanges = null;
    }

    public function detach(): void
    {
        $this->disarm();
    }

    public function describe(): string
    {
        $hold = $this->strategy->holdDuration === null ? '' : " for {$this->strategy->holdDuration}";

        return "{$this->strategy->entityId} turns {$this->strategy->state}{$hold}";
    }
}
