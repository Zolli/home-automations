<?php

declare(strict_types=1);

namespace Shared\HouseMode\Strategy;

use Closure;
use Stewart\Contracts\Exposure\Command\NumberCommand;
use Stewart\Contracts\Exposure\ExposedNumber;
use Stewart\Contracts\HaContext;
use Stewart\Contracts\Subscription;

final class ThresholdTrigger implements StrategyTrigger
{
    private float $threshold;

    /** @var (Closure(string): void)|null */
    private ?Closure $whenFired = null;

    private ?Subscription $crossings = null;

    private ?Subscription $thresholdCommands = null;

    public function __construct(
        private readonly ThresholdStrategy $strategy,
        private readonly ExposedNumber $exposedThreshold,
        private readonly HaContext $ha,
    ) {
        $this->threshold = (float) ($exposedThreshold->getValue() ?? $strategy->initialThreshold);
    }

    public function watchThresholdCommands(): void
    {
        $this->thresholdCommands ??= $this->exposedThreshold
            ->watchCommands()
            ->subscribe($this->changeThreshold(...));
    }

    public function getStrategyName(): string
    {
        return $this->strategy->name;
    }

    public function arm(Closure $whenFired): void
    {
        $this->disarm();
        $this->whenFired = $whenFired;
        $this->crossings = $this->strategy->direction
            ->filterCrossings(
                $this->ha->watchStateChanges($this->strategy->entityId),
                $this->threshold,
                $this->strategy->holdDuration,
                $this->strategy->hysteresis,
            )
            ->subscribe(fn() => $whenFired($this->strategy->name));
    }

    public function disarm(): void
    {
        $this->crossings?->unsubscribe();
        $this->crossings = null;
        $this->whenFired = null;
    }

    public function detach(): void
    {
        $this->disarm();
        $this->thresholdCommands?->unsubscribe();
        $this->thresholdCommands = null;
    }

    public function describe(): string
    {
        $hold = $this->strategy->holdDuration === null ? '' : " for {$this->strategy->holdDuration}";

        return "{$this->strategy->entityId} {$this->strategy->direction->value} {$this->threshold}{$hold}";
    }

    private function changeThreshold(NumberCommand $command): void
    {
        $this->threshold = (float) $command->value;

        if ($this->whenFired !== null) {
            $this->arm($this->whenFired);
        }
    }
}
