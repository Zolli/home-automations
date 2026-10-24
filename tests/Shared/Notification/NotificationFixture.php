<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification;

use App\Tests\SchedulerRecorder;
use Shared\Notification\Action\ActionRouter;
use Stewart\Contracts\Event\HaEvent;
use Stewart\Contracts\State\EventContext;
use Stewart\Contracts\Time\Duration;
use Stewart\Testing\App\AppHarness;
use Stewart\Testing\HaContext\RecordedActionCall;
use Stewart\Testing\HaContext\RecordingHaContext;

final readonly class NotificationFixture
{
    public AppHarness $harness;

    public RecordingHaContext $ha;

    public SchedulerRecorder $scheduler;

    public function __construct()
    {
        $this->harness   = new AppHarness();
        $this->ha        = $this->harness->ha;
        $this->scheduler = new SchedulerRecorder($this->harness->scheduler);
    }

    public function createActionRouter(): ActionRouter
    {
        return new ActionRouter($this->scheduler, $this->harness->logger);
    }

    /** @param array<string, mixed> $data */
    public function pressAction(string $key, array $data = [], ?string $userId = null): void
    {
        $this->ha->pushEvent(new HaEvent(
            ActionRouter::ACTION_EVENT,
            ['action' => $key] + $data,
            context: new EventContext('ctx', userId: $userId),
        ));
    }

    /** @param array<string, mixed> $data */
    public function dismissNotification(array $data, ?string $userId = null): void
    {
        $this->ha->pushEvent(new HaEvent(
            ActionRouter::CLEARED_EVENT,
            $data,
            context: new EventContext('ctx', userId: $userId),
        ));
    }

    /** @return list<string> */
    public function listCalledActions(): array
    {
        return $this->ha->calls->mapToList(static fn(RecordedActionCall $call): string => $call->getActionName());
    }

    public function advanceTime(Duration $duration): void
    {
        $this->harness->advanceTime($duration);
    }

    public function countActiveTasks(): int
    {
        return $this->scheduler->countActiveTasks();
    }
}
