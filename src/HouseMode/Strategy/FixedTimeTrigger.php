<?php

declare(strict_types=1);

namespace Shared\HouseMode\Strategy;

use Closure;
use Stewart\Contracts\Exposure\Command\TimeCommand;
use Stewart\Contracts\Exposure\ExposedTime;
use Stewart\Contracts\Schedule\ScheduledTask;
use Stewart\Contracts\Schedule\Scheduler;
use Stewart\Contracts\Schedule\TimeOfDay;
use Stewart\Contracts\Subscription;

final class FixedTimeTrigger implements StrategyTrigger
{
    private TimeOfDay $time;

    /** @var (Closure(string): void)|null */
    private ?Closure $whenFired = null;

    private ?ScheduledTask $dailyRun = null;

    private ?Subscription $timeCommands = null;

    public function __construct(
        private readonly FixedTimeStrategy $strategy,
        private readonly ExposedTime $exposedTime,
        private readonly Scheduler $scheduler,
    ) {
        $this->time = $exposedTime->getValue() ?? $strategy->initialTime;
    }

    public function watchTimeCommands(): void
    {
        $this->timeCommands ??= $this->exposedTime->watchCommands()->subscribe($this->changeTime(...));
    }

    public function getStrategyName(): string
    {
        return $this->strategy->name;
    }

    public function arm(Closure $whenFired): void
    {
        $this->disarm();
        $this->whenFired = $whenFired;
        $this->dailyRun  = $this->scheduler->runDailyAt(
            $this->time,
            fn() => $whenFired($this->strategy->name),
        );
    }

    public function disarm(): void
    {
        $this->dailyRun?->cancel();
        $this->dailyRun  = null;
        $this->whenFired = null;
    }

    public function detach(): void
    {
        $this->disarm();
        $this->timeCommands?->unsubscribe();
        $this->timeCommands = null;
    }

    public function describe(): string
    {
        return "daily at {$this->time->format()}";
    }

    private function changeTime(TimeCommand $command): void
    {
        $this->time = $command->value;

        if ($this->whenFired !== null) {
            $this->arm($this->whenFired);
        }
    }
}
