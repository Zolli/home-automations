<?php

declare(strict_types=1);

namespace App\Tests\Notification;

use App\Notification\NotificationMqttBridgeApp;
use App\Tests\ServiceContainer;
use App\Tests\Shared\Notification\HaRecorder;
use Closure;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Shared\Notification\DestinationType;
use Shared\Notification\Exception\InvalidDestination;
use Shared\Notification\Importance;
use Shared\Notification\Payload\NotificationActionPayloadMapper;
use Shared\Notification\Payload\NotificationPayloadMapper;
use Stewart\Contracts\EventStream;
use Stewart\Contracts\Mqtt\Mqtt;
use Stewart\Contracts\Mqtt\MqttMessage;
use Stewart\Contracts\Subscription;
use Stringable;

final class NotificationMqttBridgeAppTest extends TestCase
{
    private HaRecorder $ha;

    private NotifierRecorder $notifier;

    /** @var ?Closure(MqttMessage): void */
    private ?Closure $onMessage = null;

    private ?string $watchedTopic = null;

    /** @var list<array{string, array<array-key, mixed>|string}> */
    private array $published = [];

    /** @var object{warnings: list<string>} */
    private object $logger;

    private NotificationMqttBridgeApp $app;

    protected function setUp(): void
    {
        $this->ha = new HaRecorder($this);
        $this->notifier = new NotifierRecorder($this->ha->actionRouter());

        $subscription = $this->createStub(Subscription::class);
        $stream = $this->createStub(EventStream::class);
        $stream->method('subscribe')->willReturnCallback(function (Closure $handler) use ($subscription): Subscription {
            $this->onMessage = $handler;

            return $subscription;
        });

        $mqtt = $this->createStub(Mqtt::class);
        $mqtt->method('watchMessages')->willReturnCallback(function (string $topic) use ($stream): EventStream {
            $this->watchedTopic = $topic;

            return $stream;
        });
        $mqtt->method('publish')->willReturnCallback(function (string $topic, string|array $payload): void {
            $this->published[] = [$topic, $payload];
        });

        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $warnings = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                if ($level === 'warning') {
                    $this->warnings[] = (string) $message;
                }
            }
        };

        $this->logger = $logger;
        $this->app = new NotificationMqttBridgeApp(
            $mqtt,
            $this->ha->ha,
            $this->notifier,
            new ServiceContainer(NotificationPayloadMapper::class)->getService(NotificationPayloadMapper::class),
            new NotificationActionPayloadMapper(),
            $logger,
        );
        $this->app->initialize();
    }

    public function testListensOnDefaultTopic(): void
    {
        self::assertSame('stewart/notify', $this->watchedTopic);
    }

    public function testSendsMappedNotification(): void
    {
        $this->receive([
            'message' => ['title' => 'Vacuum', 'body' => 'Empty the bin'],
            'destinations' => [['type' => 'notify_service', 'target' => 'notify.mobile_app_zoli_phone']],
            'meta' => ['importance' => 'high'],
        ]);

        self::assertCount(1, $this->notifier->sent);
        self::assertSame('Empty the bin', $this->notifier->sent[0]->message->body);
        self::assertSame('Vacuum', $this->notifier->sent[0]->message->title);
        self::assertSame(Importance::High, $this->notifier->sent[0]->meta->importance);
    }

    public function testDropsInvalidJson(): void
    {
        $this->onMessage(new MqttMessage('stewart/notify', '{not json'));

        self::assertSame([], $this->notifier->sent);
        self::assertCount(1, $this->logger->warnings);
    }

    public function testDropsInvalidPayload(): void
    {
        $this->receive(['message' => ['body' => 'Hi'], 'destinations' => []]);

        self::assertSame([], $this->notifier->sent);
        self::assertCount(1, $this->logger->warnings);
        self::assertStringContainsString('destinations must have at least 1 items', $this->logger->warnings[0]);
    }

    public function testDropsPayloadWithRejectedDestination(): void
    {
        $this->notifier->rejectDestinations(InvalidDestination::forInvalidTarget(DestinationType::Tts, 'kitchen'));

        $this->receive(['message' => ['body' => 'Hi'], 'destinations' => [['type' => 'tts', 'target' => 'kitchen']]]);

        self::assertSame([], $this->notifier->sent);
        self::assertCount(1, $this->logger->warnings);
    }

    public function testPublishesButtonPressesToReplyTopic(): void
    {
        $this->receive($this->withButtons(['id' => 'vacuum-bin', 'replyTopic' => 'nodered/vacuum/action']));

        $this->ha->pressAction('vacuum-bin:DONE', ['reply_text' => 'emptied'], 'user-1');

        self::assertSame(
            [['nodered/vacuum/action', ['id' => 'vacuum-bin', 'action' => 'DONE', 'replyText' => 'emptied', 'userId' => 'user-1']]],
            $this->published,
        );
    }

    public function testPublishesButtonPressesToDefaultActionTopic(): void
    {
        $this->receive($this->withButtons(['id' => 'vacuum-bin']));

        $this->ha->pressAction('vacuum-bin:NEXT');

        self::assertSame([['stewart/notify/action', ['id' => 'vacuum-bin', 'action' => 'NEXT']]], $this->published);
    }

    public function testResendingSameIdPublishesOnce(): void
    {
        $this->receive($this->withButtons(['id' => 'vacuum-bin']));
        $this->receive($this->withButtons(['id' => 'vacuum-bin']));

        $this->ha->pressAction('vacuum-bin:DONE');

        self::assertCount(1, $this->published);
    }

    public function testDoesNotListenWithoutButtons(): void
    {
        $this->receive(['message' => ['body' => 'Hi'], 'destinations' => [['type' => 'notify_service', 'target' => 'notify.notify']]]);

        self::assertSame([], $this->ha->eventHandlers);
    }

    public function testDoesNotListenWhenNothingDelivered(): void
    {
        $this->notifier->stopDelivering();

        $this->receive($this->withButtons(['id' => 'vacuum-bin']));

        self::assertSame([], $this->ha->eventHandlers);
    }

    public function testDisposeStopsListeningForButtons(): void
    {
        $this->receive($this->withButtons(['id' => 'vacuum-bin']));

        $this->app->dispose();
        $this->ha->pressAction('vacuum-bin:DONE');

        self::assertSame([], $this->published);
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function withButtons(array $meta): array
    {
        return [
            'message' => [
                'body' => 'Empty the bin',
                'buttons' => [['action' => 'DONE', 'title' => 'Done'], ['action' => 'NEXT', 'title' => 'Next time']],
            ],
            'destinations' => [['type' => 'notify_service', 'target' => 'notify.mobile_app_zoli_phone']],
            'meta' => $meta,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function receive(array $payload): void
    {
        $this->onMessage(new MqttMessage('stewart/notify', json_encode($payload, \JSON_THROW_ON_ERROR)));
    }

    private function onMessage(MqttMessage $message): void
    {
        self::assertNotNull($this->onMessage);
        ($this->onMessage)($message);
    }
}
