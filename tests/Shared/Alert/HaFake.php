<?php

declare(strict_types=1);

namespace App\Tests\Shared\Alert;

use Closure;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shared\Alert\MonitoringContext;
use Shared\Notification\Action\ActionRouter;
use Stewart\Contracts\Entity\EntityId;
use Stewart\Contracts\Event\HaEvent;
use Stewart\Contracts\EventStream;
use Stewart\Contracts\HaContext;
use Stewart\Contracts\Mqtt\Mqtt;
use Stewart\Contracts\Mqtt\MqttMessage;
use Stewart\Contracts\Registry\Area;
use Stewart\Contracts\Registry\AreaId;
use Stewart\Contracts\Registry\Collection\LabelIdCollection;
use Stewart\Contracts\Registry\EntityFilter;
use Stewart\Contracts\Registry\EntityPlacement;
use Stewart\Contracts\Registry\Registry;
use Stewart\Contracts\Schedule\ScheduledTask;
use Stewart\Contracts\Schedule\Scheduler;
use Stewart\Contracts\Selector\Selector;
use Stewart\Contracts\Service\ServiceTargetSource;
use Stewart\Contracts\State\Collection\EntityStateCollection;
use Stewart\Contracts\State\EntityState;
use Stewart\Contracts\State\EventContext;
use Stewart\Contracts\State\StateChange;
use Stewart\Contracts\StateChangeStream;
use Stewart\Contracts\Subscription;
use Stewart\Contracts\Time\Clock;
use Stewart\Contracts\Time\Duration;
use Stewart\Contracts\Time\Instant;

final class HaFake
{
    private const int EPOCH_START = 1_800_000_000_000_000;

    /** @var list<array{service: string, data: array<string, mixed>, target?: array<string, list<string>>}> */
    public array $calls = [];

    public readonly HaContext $ha;

    public readonly Scheduler $scheduler;

    public readonly Mqtt $mqtt;

    public readonly Clock $clock;

    /** @var array<string, EntityState> */
    private array $states = [];

    /** @var array<int, Closure(StateChange): void> */
    private array $stateHandlers = [];

    /** @var array<string, Area> */
    private array $areasByEntityId = [];

    /** @var array<int, Closure(HaEvent): void> */
    private array $eventHandlers = [];

    /** @var array<string, array<int, Closure(MqttMessage): void>> */
    private array $mqttHandlersByTopic = [];

    /** @var array<int, array{dueAt: int, period: ?int, handler: Closure}> */
    private array $tasks = [];

    private int $now = 0;

    private int $nextKey = 0;

    /** @var array<string, array{entityId: string, state: string}> */
    private array $stateEffects = [];

    public function __construct(private readonly TestCase $test)
    {
        $ha = $this->stub(HaContext::class);
        $registry = $this->stub(Registry::class);
        $ha->method('listStates')->willReturnCallback(
            fn(Selector|EntityFilter $selector): EntityStateCollection => $selector instanceof EntityFilter
                ? EntityStateCollection::keyedByEntityId($this->states)->filterByEntityFilter($selector, $registry)
                : EntityStateCollection::keyedByEntityId($this->states)->filterBySelector($selector),
        );
        $registry->method('findEntityPlacement')->willReturnCallback(fn(EntityId|string $id): EntityPlacement => new EntityPlacement(
            EntityId::fromStringOrId($id),
            null,
            $this->areasByEntityId[(string) $id]->areaId ?? null,
            null,
            LabelIdCollection::empty(),
            true,
        ));
        $registry->method('findArea')->willReturnCallback(fn(AreaId|string $areaId): ?Area => array_find(
            $this->areasByEntityId,
            static fn(Area $area): bool => $area->areaId->value === (string) $areaId,
        ));
        $ha->method('getRegistry')->willReturn($registry);
        $ha->method('getState')->willReturnCallback(fn(EntityId|string $id): ?EntityState => $this->states[(string) $id] ?? null);
        $ha->method('watchStateChanges')->willReturnCallback(fn(): StateChangeStream => $this->createStream(StateChangeStream::class, $this->stateHandlers));
        $ha->method('watchEvents')->willReturnCallback(fn(): EventStream => $this->createStream(EventStream::class, $this->eventHandlers));
        $ha->method('callService')->willReturnCallback(
            function (string $domain, string $service, array $data = [], ?ServiceTargetSource $target = null): EventContext {
                $call = ['service' => "{$domain}.{$service}", 'data' => $data];

                if ($target !== null) {
                    $call['target'] = $target->toServiceTarget()->toArray();
                }

                $this->calls[] = $call;
                $effect = $this->stateEffects["{$domain}.{$service}"] ?? null;

                if ($effect !== null) {
                    $this->setState($effect['entityId'], $effect['state']);
                }

                return EventContext::unknown();
            },
        );
        $this->ha = $ha;

        $scheduler = $this->stub(Scheduler::class);
        $scheduler->method('runAfter')->willReturnCallback(fn(Duration $delay, Closure $handler): ScheduledTask => $this->schedule($delay, null, $handler));
        $scheduler->method('runEvery')->willReturnCallback(fn(Duration $period, Closure $handler): ScheduledTask => $this->schedule($period, $period, $handler));
        $this->scheduler = $scheduler;

        $mqtt = $this->stub(Mqtt::class);
        $mqtt->method('watchMessages')->willReturnCallback(function (string $topic): EventStream {
            $this->mqttHandlersByTopic[$topic] ??= [];

            return $this->createStream(EventStream::class, $this->mqttHandlersByTopic[$topic]);
        });
        $this->mqtt = $mqtt;

        $clock = $this->stub(Clock::class);
        $clock->method('getNow')->willReturnCallback(fn(): Instant => Instant::fromEpochMicroseconds(self::EPOCH_START + $this->now));
        $this->clock = $clock;
    }

    public function createMonitoringContext(): MonitoringContext
    {
        return new MonitoringContext($this->ha, $this->scheduler, $this->mqtt, $this->clock);
    }

    public function publishMqtt(string $topic, string $payload): void
    {
        foreach ($this->mqttHandlersByTopic[$topic] ?? [] as $handler) {
            $handler(new MqttMessage($topic, $payload));
        }
    }

    public function placeInArea(string $entityId, string $areaId, string $areaName): void
    {
        $this->areasByEntityId[$entityId] = new Area(new AreaId($areaId), $areaName);
    }

    /** @param array<string, mixed> $attributes */
    public function setState(string $entityId, string $state, array $attributes = []): void
    {
        $from = $this->states[$entityId] ?? null;
        $to = new EntityState(new EntityId($entityId), $state, $attributes);
        $this->states[$entityId] = $to;

        foreach ($this->stateHandlers as $handler) {
            $handler(new StateChange($to->entityId, $from, $to));
        }
    }

    public function changeStateOnCall(string $service, string $entityId, string $state): void
    {
        $this->stateEffects[$service] = ['entityId' => $entityId, 'state' => $state];
    }

    public function pressAction(string $key): void
    {
        $this->fireEvent(new HaEvent(ActionRouter::ACTION_EVENT, ['action' => $key], context: new EventContext('ctx', userId: 'user-1')));
    }

    public function dismissNotification(string $tag): void
    {
        $this->fireEvent(new HaEvent(ActionRouter::CLEARED_EVENT, ['tag' => $tag], context: new EventContext('ctx', userId: 'user-1')));
    }

    public function advanceBy(Duration $duration): void
    {
        $until = $this->now + $duration->toMicroseconds();

        while (($key = $this->findNextDueTask($until)) !== null) {
            $task = $this->tasks[$key];
            $this->now = $task['dueAt'];

            if ($task['period'] === null) {
                unset($this->tasks[$key]);
            } else {
                $this->tasks[$key]['dueAt'] += $task['period'];
            }

            ($task['handler'])();
        }

        $this->now = $until;
    }

    public function countPendingTasks(): int
    {
        return \count($this->tasks);
    }

    public function isWatchingStates(): bool
    {
        return $this->stateHandlers !== [];
    }

    public function isWatchingTopic(string $topic): bool
    {
        return ($this->mqttHandlersByTopic[$topic] ?? []) !== [];
    }

    private function fireEvent(HaEvent $event): void
    {
        foreach ($this->eventHandlers as $handler) {
            $handler($event);
        }
    }

    private function findNextDueTask(int $until): ?int
    {
        $next = null;

        foreach ($this->tasks as $key => $task) {
            if ($task['dueAt'] <= $until && ($next === null || $task['dueAt'] < $this->tasks[$next]['dueAt'])) {
                $next = $key;
            }
        }

        return $next;
    }

    private function schedule(Duration $delay, ?Duration $period, Closure $handler): ScheduledTask
    {
        $key = $this->nextKey++;
        $this->tasks[$key] = ['dueAt' => $this->now + $delay->toMicroseconds(), 'period' => $period?->toMicroseconds(), 'handler' => $handler];

        $task = $this->stub(ScheduledTask::class);
        $task->method('isActive')->willReturnCallback(fn(): bool => \array_key_exists($key, $this->tasks));
        $task->method('cancel')->willReturnCallback(function () use ($key): void {
            unset($this->tasks[$key]);
        });

        return $task;
    }

    /**
     * @template T of EventStream
     * @param class-string<T> $class
     * @param array<int, Closure> $handlers
     * @return T
     */
    private function createStream(string $class, array &$handlers): EventStream
    {
        $stream = $this->stub($class);
        $stream->method('subscribe')->willReturnCallback(function (Closure $handler) use (&$handlers): Subscription {
            $handlers[] = $handler;
            $key = array_key_last($handlers);
            $subscription = $this->stub(Subscription::class);
            $subscription->method('unsubscribe')->willReturnCallback(function () use (&$handlers, $key): void {
                unset($handlers[$key]);
            });

            return $subscription;
        });

        return $stream;
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return T&Stub
     */
    private function stub(string $class): object
    {
        return (fn() => $this->createStub($class))->call($this->test);
    }
}
