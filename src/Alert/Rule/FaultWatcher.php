<?php

declare(strict_types=1);

namespace Shared\Alert\Rule;

use Closure;
use Shared\Alert\Reading\ReadingFeed;
use Stewart\Contracts\Schedule\ScheduledTask;
use Stewart\Contracts\Schedule\Scheduler;
use Stewart\Contracts\Time\Duration;

final class FaultWatcher
{
    private ?ScheduledTask $pendingFault = null;

    private ?ScheduledTask $recheck = null;

    private bool $faultRaised = false;

    private bool $notifyingListener = false;

    private bool $readingsChangedWhileNotifying = false;

    public function __construct(
        private readonly AlertRule $rule,
        private readonly ReadingFeed $feed,
        private readonly Scheduler $scheduler,
        private readonly FaultListener $listener,
    ) {}

    public function startWatching(): void
    {
        $this->feed->startWatching($this->evaluateReadings(...));

        if ($this->rule instanceof RecheckedAlertRule) {
            $this->recheck ??= $this->scheduler->runEvery($this->rule->getRecheckInterval(), fn() => $this->evaluateReadings());
        }

        $this->evaluateReadings();
    }

    public function stopWatching(): void
    {
        $this->feed->stopWatching();
        $this->recheck?->cancel();
        $this->recheck = null;
        $this->cancelPendingFault();
    }

    private function evaluateReadings(): void
    {
        if ($this->notifyingListener) {
            $this->readingsChangedWhileNotifying = true;

            return;
        }

        $snapshot = $this->feed->readSnapshot();

        if ($this->faultRaised) {
            if ($this->rule->isRecovered($snapshot)) {
                $this->faultRaised = false;
                $this->notifyListener($this->listener->clearFault(...));
            }

            return;
        }

        $fault = $this->rule->findFault($snapshot);

        if ($fault === null) {
            $this->cancelPendingFault();

            return;
        }

        if ($this->pendingFault?->isActive()) {
            return;
        }

        if (!$this->rule->getHoldDuration()->isLongerThan(Duration::zero())) {
            $this->raiseFault($fault);

            return;
        }

        $this->pendingFault = $this->scheduler->runAfter($this->rule->getHoldDuration(), $this->raiseHeldFault(...));
    }

    private function raiseHeldFault(): void
    {
        $this->pendingFault = null;
        $fault = $this->rule->findFault($this->feed->readSnapshot());

        if ($fault !== null) {
            $this->raiseFault($fault);
        }
    }

    private function raiseFault(Fault $fault): void
    {
        $this->faultRaised = true;
        $this->notifyListener(fn() => $this->listener->raiseFault($fault));
    }

    // Mitigations change watched readings while the listener still runs; those changes are evaluated once it returns.
    private function notifyListener(Closure $notification): void
    {
        $this->notifyingListener = true;

        try {
            $notification();
        } finally {
            $this->notifyingListener = false;
        }

        if ($this->readingsChangedWhileNotifying) {
            $this->readingsChangedWhileNotifying = false;
            $this->evaluateReadings();
        }
    }

    private function cancelPendingFault(): void
    {
        $this->pendingFault?->cancel();
        $this->pendingFault = null;
    }
}
