<?php

declare(strict_types=1);

namespace Shared\HouseMode\Trigger;

use Closure;
use Shared\HouseMode\TransitionContext;
use Stewart\Contracts\Exposure\Command\TimeCommand;
use Stewart\Contracts\Schedule\ScheduledTask;
use Stewart\Contracts\Schedule\TimeOfDay;
use Stewart\Contracts\Subscription;

final class FixedTimeTrigger implements ModeTrigger
{
    private TimeOfDay $time;

    private ?ScheduledTask $dailyRun = null;

    private ?Subscription $timeCommands = null;

    /** @var (Closure(): void)|null */
    private ?Closure $rearm = null;

    public function __construct(
        private readonly string $name,
        private readonly TimeOfDay $initialTime,
    ) {
        $this->time = $initialTime;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function attach(TransitionContext $context): void
    {
        $exposedTime        = $context->exposeTime($this->name, $this->initialTime);
        $this->time         = $exposedTime->getValue() ?? $this->initialTime;
        $this->timeCommands = $exposedTime->watchCommands()->subscribe($this->changeTime(...));
    }

    public function arm(TransitionContext $context, Closure $whenFired): void
    {
        $this->disarm();
        $this->dailyRun = $context->mode->scheduler->runDailyAt($this->time, fn() => $whenFired($this->name));
        $this->rearm    = fn() => $this->arm($context, $whenFired);
    }

    public function disarm(): void
    {
        $this->dailyRun?->cancel();
        $this->dailyRun = null;
        $this->rearm    = null;
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

        if ($this->rearm !== null) {
            ($this->rearm)();
        }
    }
}
