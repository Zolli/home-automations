<?php

declare(strict_types=1);

namespace App\Tests;

use Closure;
use DateTimeImmutable;
use Stewart\Contracts\Entity\EntityId;
use Stewart\Contracts\Schedule\DayOfWeek;
use Stewart\Contracts\Schedule\ScheduledTask;
use Stewart\Contracts\Schedule\Scheduler;
use Stewart\Contracts\Schedule\TimeOfDay;
use Stewart\Contracts\Sun\SunEvent;
use Stewart\Contracts\Time\Duration;
use Stewart\Contracts\Time\SunOffset;

final class SchedulerRecorder implements Scheduler
{
    /** @var list<ScheduledTask> */
    private array $tasks = [];

    public function __construct(
        private readonly Scheduler $scheduler,
    ) {}

    public function runEvery(Duration $period, Closure $handler): ScheduledTask
    {
        return $this->recordTask($this->scheduler->runEvery($period, $handler));
    }

    public function runDailyAt(TimeOfDay|string $time, Closure $handler, DayOfWeek ...$onlyOn): ScheduledTask
    {
        return $this->recordTask($this->scheduler->runDailyAt($time, $handler, ...$onlyOn));
    }

    public function runOnCron(string $expression, Closure $handler): ScheduledTask
    {
        return $this->recordTask($this->scheduler->runOnCron($expression, $handler));
    }

    public function runAfter(Duration $delay, Closure $handler): ScheduledTask
    {
        return $this->recordTask($this->scheduler->runAfter($delay, $handler));
    }

    public function runAt(DateTimeImmutable $moment, Closure $handler): ScheduledTask
    {
        return $this->recordTask($this->scheduler->runAt($moment, $handler));
    }

    public function runAtEntityTime(EntityId|string $entity, Closure $handler): ScheduledTask
    {
        return $this->recordTask($this->scheduler->runAtEntityTime($entity, $handler));
    }

    public function runAtSunEvent(SunEvent $event, Closure $handler, ?SunOffset $offset = null): ScheduledTask
    {
        return $this->recordTask($this->scheduler->runAtSunEvent($event, $handler, $offset));
    }

    public function waitFor(Closure $arm, ?Duration $timeout = null): mixed
    {
        return $this->scheduler->waitFor($arm, $timeout);
    }

    public function countActiveTasks(): int
    {
        return \count(array_filter($this->tasks, static fn(ScheduledTask $task): bool => $task->isActive()));
    }

    private function recordTask(ScheduledTask $task): ScheduledTask
    {
        return $this->tasks[] = $task;
    }
}
