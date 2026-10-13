<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification;

use Shared\Notification\Action\ActionRouter;
use Stewart\Contracts\Event\HaEvent;
use Stewart\Contracts\State\EventContext;
use Stewart\Contracts\Time\Duration;
use Stewart\Testing\App\AppHarness;
use Stewart\Testing\HaContext\RecordedServiceCall;
use Stewart\Testing\HaContext\RecordingHaContext;

final readonly class NotificationFixture
{
    public AppHarness $harness;

    public RecordingHaContext $ha;

    public function __construct()
    {
        $this->harness = new AppHarness();
        $this->ha = $this->harness->ha;
    }

    public function createActionRouter(): ActionRouter
    {
        return new ActionRouter($this->harness->scheduler, $this->harness->logger);
    }

    /** @param array<string, mixed> $data */
    public function pressAction(string $key, array $data = [], ?string $userId = null): void
    {
        $this->ha->pushEvent(new HaEvent(ActionRouter::ACTION_EVENT, ['action' => $key] + $data, context: new EventContext('ctx', userId: $userId)));
    }

    /** @param array<string, mixed> $data */
    public function dismissNotification(array $data, ?string $userId = null): void
    {
        $this->ha->pushEvent(new HaEvent(ActionRouter::CLEARED_EVENT, $data, context: new EventContext('ctx', userId: $userId)));
    }

    /** @return list<string> */
    public function listCalledServices(): array
    {
        return $this->ha->calls->mapToList(static fn(RecordedServiceCall $call): string => $call->getServiceName());
    }

    public function advanceTime(Duration $duration): void
    {
        $this->harness->advanceTime($duration);
    }
}
