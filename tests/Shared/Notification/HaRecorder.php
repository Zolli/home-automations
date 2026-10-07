<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification;

use Closure;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shared\Notification\Action\ActionRouter;
use Stewart\Contracts\Entity\EntityId;
use Stewart\Contracts\Event\HaEvent;
use Stewart\Contracts\EventStream;
use Stewart\Contracts\HaContext;
use Stewart\Contracts\Schedule\ScheduledTask;
use Stewart\Contracts\Schedule\Scheduler;
use Stewart\Contracts\Service\ServiceTargetSource;
use Stewart\Contracts\State\EntityState;
use Stewart\Contracts\State\EventContext;
use Stewart\Contracts\Subscription;

final class HaRecorder
{
    /** @var list<array{service: string, data: array<string, mixed>, target?: array<string, list<string>>}> */
    public array $calls = [];

    /** @var array<int, Closure(HaEvent): void> */
    public array $eventHandlers = [];

    /** @var array<int, Closure(): void> */
    public array $scheduled = [];

    public readonly HaContext $ha;

    public readonly Scheduler $scheduler;

    /** @param array<string, string> $states */
    public function __construct(private readonly TestCase $test, array $states = [])
    {
        $ha = $this->stub(HaContext::class);
        $ha->method('getState')->willReturnCallback(
            static fn(EntityId|string $id): ?EntityState => isset($states[(string) $id])
                ? new EntityState(new EntityId((string) $id), $states[(string) $id])
                : null,
        );
        $ha->method('callService')->willReturnCallback(
            function (string $domain, string $service, array $data = [], ?ServiceTargetSource $target = null): EventContext {
                $call = ['service' => "{$domain}.{$service}", 'data' => $data];

                if ($target !== null) {
                    $call['target'] = $target->toServiceTarget()->toArray();
                }

                $this->calls[] = $call;

                return EventContext::unknown();
            },
        );
        $ha->method('watchEvents')->willReturnCallback(fn(string $type): EventStream => $this->eventStream());
        $this->ha = $ha;

        $scheduler = $this->stub(Scheduler::class);
        $scheduler->method('runAfter')->willReturnCallback(function (mixed $delay, Closure $handler): ScheduledTask {
            $this->scheduled[] = $handler;
            $key = array_key_last($this->scheduled);
            $task = $this->stub(ScheduledTask::class);
            $task->method('cancel')->willReturnCallback(function () use ($key): void {
                unset($this->scheduled[$key]);
            });

            return $task;
        });
        $this->scheduler = $scheduler;
    }

    public function actionRouter(LoggerInterface $logger = new NullLogger()): ActionRouter
    {
        return new ActionRouter($this->scheduler, $logger);
    }

    /** @param array<string, mixed> $data */
    public function pressAction(string $key, array $data = [], ?string $userId = null): void
    {
        $event = new HaEvent(ActionRouter::EVENT, ['action' => $key] + $data, context: new EventContext('ctx', userId: $userId));

        foreach ($this->eventHandlers as $handler) {
            $handler($event);
        }
    }

    public function runScheduled(): void
    {
        foreach ($this->scheduled as $handler) {
            $handler();
        }
    }

    /** @return EventStream<HaEvent> */
    private function eventStream(): EventStream
    {
        $stream = $this->stub(EventStream::class);
        $stream->method('subscribe')->willReturnCallback(function (Closure $handler): Subscription {
            $this->eventHandlers[] = $handler;
            $key = array_key_last($this->eventHandlers);
            $subscription = $this->stub(Subscription::class);
            $subscription->method('unsubscribe')->willReturnCallback(function () use ($key): void {
                unset($this->eventHandlers[$key]);
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
