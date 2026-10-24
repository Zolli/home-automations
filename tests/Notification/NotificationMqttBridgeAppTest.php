<?php

declare(strict_types=1);

namespace App\Tests\Notification;

use App\Notification\NotificationMqttBridgeApp;
use App\Tests\ServiceContainer;
use App\Tests\Shared\Notification\NotificationFixture;
use PHPUnit\Framework\TestCase;
use Shared\Notification\DestinationType;
use Shared\Notification\Exception\InvalidDestination;
use Shared\Notification\Importance;
use Shared\Notification\Notifier;
use Shared\Notification\Payload\NotificationActionPayloadMapper;
use Shared\Notification\Payload\NotificationPayloadMapper;
use Stewart\Contracts\Mqtt\MqttMessage;
use Stewart\Testing\Logging\RecordingLogger;

final class NotificationMqttBridgeAppTest extends TestCase
{
    private const string NOTIFY_TOPIC = 'stewart/notify';

    private NotificationFixture $fixture;

    private NotifierRecorder $notifier;

    private RecordingLogger $logger;

    protected function setUp(): void
    {
        $this->fixture  = new NotificationFixture();
        $harness        = $this->fixture->harness;
        $this->notifier = new NotifierRecorder($this->fixture->createActionRouter());
        $this->logger   = $harness->logger;
        $harness->provideService(Notifier::class, $this->notifier);
        $harness->provideService(
            NotificationPayloadMapper::class,
            new ServiceContainer(NotificationPayloadMapper::class)->getService(NotificationPayloadMapper::class),
        );
        $harness->provideService(NotificationActionPayloadMapper::class, new NotificationActionPayloadMapper());
        $harness->startApp(NotificationMqttBridgeApp::class);
    }

    public function testListensOnDefaultTopicOnly(): void
    {
        $this->receive($this->withButtons([]), 'other/notify');
        self::assertSame([], $this->notifier->sent);

        $this->receive($this->withButtons([]));
        self::assertCount(1, $this->notifier->sent);
    }

    public function testSendsMappedNotification(): void
    {
        $this->receive([
            'message'      => ['title' => 'Vacuum', 'body' => 'Empty the bin'],
            'destinations' => [['type' => 'notify_service', 'target' => 'notify.mobile_app_zoli_phone']],
            'meta'         => ['importance' => 'high'],
        ]);

        self::assertCount(1, $this->notifier->sent);
        self::assertSame('Empty the bin', $this->notifier->sent[0]->message->body);
        self::assertSame('Vacuum', $this->notifier->sent[0]->message->title);
        self::assertSame(Importance::High, $this->notifier->sent[0]->meta->importance);
    }

    public function testDropsInvalidJson(): void
    {
        $this->fixture->harness->mqtt->pushMessage(new MqttMessage(self::NOTIFY_TOPIC, '{not json'));

        self::assertSame([], $this->notifier->sent);
        self::assertCount(1, $this->logger->listMessagesAt('warning'));
    }

    public function testDropsInvalidPayload(): void
    {
        $this->receive(['message' => ['body' => 'Hi'], 'destinations' => []]);

        self::assertSame([], $this->notifier->sent);
        self::assertCount(1, $this->logger->listMessagesAt('warning'));
        self::assertStringContainsString(
            'destinations must have at least 1 items',
            $this->logger->listMessagesAt('warning')[0],
        );
    }

    public function testDropsPayloadWithRejectedDestination(): void
    {
        $this->notifier->rejectDestinations(InvalidDestination::forInvalidTarget(DestinationType::Tts, 'kitchen'));

        $this->receive(['message' => ['body' => 'Hi'], 'destinations' => [['type' => 'tts', 'target' => 'kitchen']]]);

        self::assertSame([], $this->notifier->sent);
        self::assertCount(1, $this->logger->listMessagesAt('warning'));
    }

    public function testPublishesButtonPressesToReplyTopic(): void
    {
        $this->receive($this->withButtons(['id' => 'vacuum-bin', 'replyTopic' => 'nodered/vacuum/action']));

        $this->fixture->pressAction('vacuum-bin:DONE', ['reply_text' => 'emptied'], 'user-1');

        self::assertSame(
            [[
                'nodered/vacuum/action',
                ['id' => 'vacuum-bin', 'action' => 'DONE', 'replyText' => 'emptied', 'userId' => 'user-1'],
            ]],
            $this->listPublished(),
        );
    }

    public function testPublishesButtonPressesToDefaultActionTopic(): void
    {
        $this->receive($this->withButtons(['id' => 'vacuum-bin']));

        $this->fixture->pressAction('vacuum-bin:NEXT');

        self::assertSame(
            [['stewart/notify/action', ['id' => 'vacuum-bin', 'action' => 'NEXT']]],
            $this->listPublished(),
        );
    }

    public function testResendingSameIdPublishesOnce(): void
    {
        $this->receive($this->withButtons(['id' => 'vacuum-bin']));
        $this->receive($this->withButtons(['id' => 'vacuum-bin']));

        $this->fixture->pressAction('vacuum-bin:DONE');

        self::assertCount(1, $this->listPublished());
    }

    public function testDoesNotListenWithoutButtons(): void
    {
        $this->receive([
            'message'      => ['body' => 'Hi'],
            'destinations' => [['type' => 'notify_service', 'target' => 'notify.notify']],
        ]);

        self::assertSame(0, $this->fixture->countActiveTasks());
    }

    public function testDoesNotListenWhenNothingDelivered(): void
    {
        $this->notifier->stopDelivering();

        $this->receive($this->withButtons(['id' => 'vacuum-bin']));

        self::assertSame(0, $this->fixture->countActiveTasks());
    }

    public function testDisposeStopsListeningForButtons(): void
    {
        $this->receive($this->withButtons(['id' => 'vacuum-bin']));

        $this->fixture->harness->stopApp();
        $this->fixture->pressAction('vacuum-bin:DONE');

        self::assertSame([], $this->listPublished());
    }

    /**
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    private function withButtons(array $meta): array
    {
        return [
            'message'      => [
                'body'    => 'Empty the bin',
                'buttons' => [['action' => 'DONE', 'title' => 'Done'], ['action' => 'NEXT', 'title' => 'Next time']],
            ],
            'destinations' => [['type' => 'notify_service', 'target' => 'notify.mobile_app_zoli_phone']],
            'meta'         => $meta,
        ];
    }

    /** @param array<string, mixed> $payload */
    private function receive(array $payload, string $topic = self::NOTIFY_TOPIC): void
    {
        $this->fixture->harness->mqtt->pushMessage(
            new MqttMessage($topic, json_encode($payload, \JSON_THROW_ON_ERROR)),
        );
    }

    /** @return list<array{string, mixed}> */
    private function listPublished(): array
    {
        return $this->fixture->harness->mqtt->published->mapToList(
            static fn(MqttMessage $message): array => [$message->topic, $message->decodeJsonPayload()],
        );
    }
}
