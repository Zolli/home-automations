<?php

declare(strict_types=1);

namespace App\Tests\Shared\HouseMode;

use DateTimeImmutable;
use DateTimeZone;
use Shared\HouseMode\ModeContext;
use Shared\HouseMode\TransitionContext;
use Stewart\Contracts\Entity\EntityId;
use Stewart\Contracts\Exposure\Command\NumberCommand;
use Stewart\Contracts\Exposure\Command\SelectCommand;
use Stewart\Contracts\Exposure\Command\TimeCommand;
use Stewart\Contracts\Exposure\DeviceInfo;
use Stewart\Contracts\Exposure\ExposedEntitySnapshot;
use Stewart\Contracts\Exposure\ExposedState;
use Stewart\Contracts\Mqtt\MqttMessage;
use Stewart\Contracts\Schedule\TimeOfDay;
use Stewart\Contracts\State\EventContext;
use Stewart\Contracts\Time\Duration;
use Stewart\Testing\App\AppHarness;
use Stewart\Testing\HaContext\RecordedServiceCall;

final readonly class ModeFixture
{
    public AppHarness $harness;

    public function __construct(string $startTime = '2026-10-14 12:00')
    {
        $this->harness = new AppHarness(new DateTimeImmutable($startTime, new DateTimeZone('Europe/Budapest')));
    }

    public function createModeContext(): ModeContext
    {
        return new ModeContext(
            $this->harness->ha,
            $this->harness->scheduler,
            $this->harness->clock,
            $this->harness->entities,
            new DeviceInfo('house_mode', 'House mode'),
            $this->harness->logger,
        );
    }

    public function createTransitionContext(string $transitionName): TransitionContext
    {
        return new TransitionContext($this->createModeContext(), $transitionName);
    }

    public function restoreExposedValue(string $key, int|float|string|null $value): void
    {
        $this->harness->entities->seedSnapshot(
            $key,
            new ExposedEntitySnapshot(new EntityId("stewart.{$key}"), new ExposedState($value), [], true),
        );
    }

    public function selectOption(string $key, string $option): void
    {
        $this->harness->entities->requireSelect($key)->pushCommand(new SelectCommand($option, $this->createContext()));
    }

    public function changeTime(string $key, string $time): void
    {
        $this->harness->entities
            ->requireTime($key)
            ->pushCommand(new TimeCommand(TimeOfDay::parse($time), $this->createContext()));
    }

    public function changeNumber(string $key, float $value): void
    {
        $this->harness->entities->requireNumber($key)->pushCommand(new NumberCommand($value, $this->createContext()));
    }

    /** @return list<string> */
    public function listCalledServices(): array
    {
        return $this->harness->ha->calls->mapToList(
            static fn(RecordedServiceCall $call): string => $call->getServiceName(),
        );
    }

    /** @return list<array{string, mixed}> */
    public function listPublishedMessages(): array
    {
        return $this->harness->mqtt->published->mapToList(
            static fn(MqttMessage $message): array => [$message->topic, $message->decodeJsonPayload()],
        );
    }

    public function advanceTime(Duration $duration): void
    {
        $this->harness->advanceTime($duration);
    }

    private function createContext(): EventContext
    {
        return new EventContext('ctx');
    }
}
