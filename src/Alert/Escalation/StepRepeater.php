<?php

declare(strict_types=1);

namespace Shared\Alert\Escalation;

use Closure;
use Shared\Condition\ConditionContext;
use Stewart\Contracts\HaContext;
use Stewart\Contracts\Schedule\ScheduledTask;
use Stewart\Contracts\Schedule\Scheduler;

final class StepRepeater
{
    private int $repeatsSent = 0;

    private ?ScheduledTask $task = null;

    /** @param Closure(): void $sendRepeat */
    public function __construct(
        private readonly StepRepetition $repetition,
        private readonly Closure $sendRepeat,
        private readonly Scheduler $scheduler,
        private readonly HaContext $ha,
    ) {}

    public function startRepeating(): void
    {
        $this->task ??= $this->scheduler->runEvery($this->repetition->every, fn() => $this->repeatOnce());
    }

    public function stopRepeating(): void
    {
        $this->task?->cancel();
        $this->task = null;
    }

    private function repeatOnce(): void
    {
        if (!$this->repetition->allowsAnotherRepeat($this->repeatsSent) || !$this->repetition->while->isSatisfied(new ConditionContext($this->ha))) {
            $this->stopRepeating();

            return;
        }

        $this->repeatsSent++;
        ($this->sendRepeat)();
    }
}
