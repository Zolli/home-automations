<?php

declare(strict_types=1);

namespace App\Tests\Shared\Alert;

use Shared\Alert\MonitoringContext;
use Shared\Notification\Action\ActionRouter;
use Stewart\Contracts\Entity\EntityId;
use Stewart\Contracts\Event\HaEvent;
use Stewart\Contracts\Mqtt\MqttMessage;
use Stewart\Contracts\Registry\Area;
use Stewart\Contracts\Registry\AreaId;
use Stewart\Contracts\Registry\RegisteredEntity;
use Stewart\Contracts\State\EventContext;
use Stewart\Contracts\Time\Duration;
use Stewart\Testing\App\AppHarness;
use Stewart\Testing\HaContext\RecordedServiceCall;
use Stewart\Testing\HaContext\RecordingHaContext;

final readonly class AlertFixture
{
    private const string ACTING_USER_ID = 'user-1';

    public AppHarness $harness;

    public RecordingHaContext $ha;

    public function __construct()
    {
        $this->harness = new AppHarness();
        $this->ha      = $this->harness->ha;
    }

    public function createMonitoringContext(): MonitoringContext
    {
        return new MonitoringContext($this->ha, $this->harness->scheduler, $this->harness->mqtt, $this->harness->clock);
    }

    public function placeInArea(string $entityId, string $areaId, string $areaName): void
    {
        $this->ha->registry
            ->seedArea(new Area(new AreaId($areaId), $areaName))
            ->seedEntity(new RegisteredEntity(new EntityId($entityId), areaId: new AreaId($areaId)));
    }

    public function publishMqtt(string $topic, string $payload): void
    {
        $this->harness->mqtt->pushMessage(new MqttMessage($topic, $payload));
    }

    public function pressAction(string $key): void
    {
        $this->ha->pushEvent(new HaEvent(
            ActionRouter::ACTION_EVENT,
            ['action' => $key],
            context: new EventContext('ctx', userId: self::ACTING_USER_ID),
        ));
    }

    public function dismissNotification(string $tag): void
    {
        $this->ha->pushEvent(new HaEvent(
            ActionRouter::CLEARED_EVENT,
            ['tag' => $tag],
            context: new EventContext('ctx', userId: self::ACTING_USER_ID),
        ));
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

    public function countPendingTimers(): int
    {
        return $this->harness->timers->countPendingTimers();
    }
}
