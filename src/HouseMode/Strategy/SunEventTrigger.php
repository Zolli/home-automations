<?php

declare(strict_types=1);

namespace Shared\HouseMode\Strategy;

use Closure;
use Stewart\Contracts\Schedule\ScheduledTask;
use Stewart\Contracts\Schedule\Scheduler;

final class SunEventTrigger implements StrategyTrigger
{
    private ?ScheduledTask $sunEventRun = null;

    public function __construct(
        private readonly SunEventStrategy $strategy,
        private readonly Scheduler $scheduler,
    ) {}

    public function getStrategyName(): string
    {
        return $this->strategy->name;
    }

    public function arm(Closure $whenFired): void
    {
        $this->disarm();
        $this->sunEventRun = $this->scheduler->runAtSunEvent(
            $this->strategy->event,
            fn() => $whenFired($this->strategy->name),
            $this->strategy->offset,
        );
    }

    public function disarm(): void
    {
        $this->sunEventRun?->cancel();
        $this->sunEventRun = null;
    }

    public function detach(): void
    {
        $this->disarm();
    }

    public function describe(): string
    {
        $offset = $this->strategy->offset === null ? '' : " {$this->strategy->offset->formatAsHaOffset()}";

        return "at {$this->strategy->event->value}{$offset}";
    }
}
