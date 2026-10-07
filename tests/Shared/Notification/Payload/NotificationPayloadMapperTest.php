<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification\Payload;

use App\Tests\ServiceContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Condition\AllOf;
use Shared\Condition\CompareCondition;
use Shared\Condition\Operator;
use Shared\Notification\Action\NotificationButton;
use Shared\Notification\Condition\ImportanceCondition;
use Shared\Notification\DestinationType;
use Shared\Notification\Importance;
use Shared\Notification\Payload\InvalidNotificationPayload;
use Shared\Notification\Payload\NotificationPayloadMapper;
use Shared\Notification\Sender\NotifyServiceSender;
use Stewart\Contracts\Entity\EntityId;

final class NotificationPayloadMapperTest extends TestCase
{
    private const string NODE_RED_PAYLOAD = <<<'JSON'
        {
            "message": {
                "title": "Empty the vacuum bin",
                "body": "Dusty is full on debreis, please empty it",
                "additionalData": {
                    "data": {
                        "notification_icon": "mdi:robot-vacuum-variant",
                        "color": "#f47100"
                    }
                },
                "buttons": [
                    {"action": "DONE", "title": "Done"},
                    {"action": "NEXT", "title": "Next time"}
                ]
            },
            "destinations": [
                {
                    "type": "notify_service",
                    "target": "notify.mobile_app_zoli_phone",
                    "conditions": [{"type": "importance", "field": "input_select.zoli_notification_level"}]
                },
                {
                    "type": "notify_service",
                    "target": "notify.mobile_app_zsuzsi_phone",
                    "conditions": [{"type": "importance", "field": "input_select.zsuzsi_notification_level"}]
                }
            ],
            "meta": {
                "importance": "low",
                "debug": true
            }
        }
        JSON;

    private NotificationPayloadMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ServiceContainer(NotificationPayloadMapper::class)->getService(NotificationPayloadMapper::class);
    }

    public function testMapsNodeRedPayload(): void
    {
        $notification = $this->mapper->mapToNotificationRequest(json_decode(self::NODE_RED_PAYLOAD, true))->notification;

        self::assertSame('Empty the vacuum bin', $notification->message->title);
        self::assertSame('Dusty is full on debreis, please empty it', $notification->message->body);
        self::assertSame(
            ['data' => ['notification_icon' => 'mdi:robot-vacuum-variant', 'color' => '#f47100']],
            $notification->message->additionalData,
        );
        self::assertEquals(
            [new NotificationButton('DONE', 'Done'), new NotificationButton('NEXT', 'Next time')],
            $notification->message->buttons->listValues(),
        );
        self::assertSame(Importance::Low, $notification->meta->importance);
        self::assertTrue($notification->meta->debug);
        self::assertCount(2, $notification->destinations);

        [$zoli, $zsuzsi] = $notification->destinations->listValues();
        self::assertEquals(DestinationType::NotifyService, $zoli->type);
        self::assertSame(['notify.mobile_app_zoli_phone'], $zoli->targets);
        self::assertEquals(new AllOf(new ImportanceCondition(new EntityId('input_select.zoli_notification_level'))), $zoli->condition);
        self::assertSame(['notify.mobile_app_zsuzsi_phone'], $zsuzsi->targets);
    }

    public function testMapsOptionalExtensions(): void
    {
        $request = $this->mapper->mapToNotificationRequest([
            'message' => ['body' => 'Laundry done'],
            'destinations' => [[
                'type' => 'tts',
                'target' => ['media_player.kitchen_speaker', 'media_player.living_room_speaker'],
                'options' => ['chime_path' => 'bells', 'volume_level' => 0.5],
                'conditions' => [['type' => 'lt', 'field' => 'sun.sun', 'attribute' => 'elevation', 'value' => 10]],
            ]],
            'meta' => ['id' => 'laundry', 'replyTopic' => 'nodered/laundry/action', 'importance' => 'high'],
        ]);
        $notification = $request->notification;

        $destination = $notification->destinations->listValues()[0];
        self::assertSame(['media_player.kitchen_speaker', 'media_player.living_room_speaker'], $destination->targets);
        self::assertSame(['chime_path' => 'bells', 'volume_level' => 0.5], $destination->options);
        self::assertEquals(new AllOf(new CompareCondition(new EntityId('sun.sun'), Operator::Lt, 10, 'elevation')), $destination->condition);
        self::assertSame('laundry', $notification->meta->id->value);
        self::assertSame('nodered/laundry/action', $request->replyTopic);
        self::assertSame(Importance::High, $notification->meta->importance);
    }

    public function testPassesExtraButtonKeysAsOptions(): void
    {
        $notification = $this->mapper->mapToNotificationRequest([
            'message' => ['body' => 'Hi', 'buttons' => [['action' => 'URI', 'title' => 'Open', 'uri' => '/lovelace/vacuum']]],
            'destinations' => [['type' => 'notify_service', 'target' => 'notify.notify']],
        ])->notification;

        self::assertEquals(
            [new NotificationButton('URI', 'Open', ['uri' => '/lovelace/vacuum'])],
            $notification->message->buttons->listValues(),
        );
    }

    public function testMetaIsOptional(): void
    {
        $notification = $this->mapper->mapToNotificationRequest([
            'message' => ['body' => 'Hi'],
            'destinations' => [['type' => 'notify_service', 'target' => 'notify.notify']],
        ])->notification;

        self::assertSame(Importance::Normal, $notification->meta->importance);
        self::assertFalse($notification->meta->debug);
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function invalidPayloads(): iterable
    {
        $destination = ['type' => 'notify_service', 'target' => 'notify.notify'];

        yield 'not an object' => ['hello', 'payload must be of type object'];
        yield 'missing message' => [['destinations' => [$destination]], 'message is required'];
        yield 'missing body' => [['message' => ['title' => 'Hi'], 'destinations' => [$destination]], 'message.body is required'];
        yield 'empty body' => [['message' => ['body' => ' '], 'destinations' => [$destination]], 'message.body has an invalid format'];
        yield 'title not a string' => [['message' => ['body' => 'Hi', 'title' => 5], 'destinations' => [$destination]], 'message.title must be of type string'];
        yield 'missing destinations' => [['message' => ['body' => 'Hi']], 'destinations is required'];
        yield 'no destinations' => [['message' => ['body' => 'Hi'], 'destinations' => []], 'destinations must have at least 1 items'];
        yield 'destination without type' => [['message' => ['body' => 'Hi'], 'destinations' => [['target' => 'notify.notify']]], 'destinations[0].type is required'];
        yield 'destination without target' => [['message' => ['body' => 'Hi'], 'destinations' => [['type' => 'tts']]], 'destinations[0].target is required'];
        yield 'unknown destination type' => [
            ['message' => ['body' => 'Hi'], 'destinations' => [['type' => 'telegram', 'target' => 'chat']]],
            'destinations[0].type Destination type "telegram" is unknown, expected one of: notify_service, tts.',
        ];
        yield 'target list with a number' => [['message' => ['body' => 'Hi'], 'destinations' => [['type' => 'tts', 'target' => [1]]]], 'destinations[0].target[0] must be of type string'];
        yield 'non-numeric ordering condition' => [
            ['message' => ['body' => 'Hi'], 'destinations' => [$destination + ['conditions' => [['type' => 'gt', 'field' => 'sensor.temperature', 'value' => 'warm']]]]],
            'destinations[0].conditions[0] Condition "gt" needs a numeric "value"',
        ];
        yield 'unknown condition' => [
            ['message' => ['body' => 'Hi'], 'destinations' => [$destination + ['conditions' => [['type' => 'between']]]]],
            'destinations[0].conditions[0] Condition type "between" is unknown',
        ];
        yield 'unknown importance' => [['message' => ['body' => 'Hi'], 'destinations' => [$destination], 'meta' => ['importance' => 'urgent']], 'meta.importance Importance "urgent" is unknown, expected one of: low, normal, high, critical.'];
        yield 'debug not a boolean' => [['message' => ['body' => 'Hi'], 'destinations' => [$destination], 'meta' => ['debug' => 'yes']], 'meta.debug must be of type boolean'];
        yield 'button without title' => [
            ['message' => ['body' => 'Hi', 'buttons' => [['action' => 'DONE']]], 'destinations' => [$destination]],
            'message.buttons[0].title is required',
        ];
        yield 'uppercase importance' => [
            ['message' => ['body' => 'Hi'], 'destinations' => [$destination], 'meta' => ['importance' => 'HIGH']],
            'meta.importance Importance "HIGH" is unknown',
        ];
        yield 'blank button action' => [
            ['message' => ['body' => 'Hi', 'buttons' => [['action' => ' ', 'title' => 'Done']]], 'destinations' => [$destination]],
            'message.buttons[0] Notification button action must not be empty.',
        ];
        yield 'invalid id' => [['message' => ['body' => 'Hi'], 'destinations' => [$destination], 'meta' => ['id' => 'a:b']], 'meta.id Notification id may only contain'];
    }

    #[DataProvider('invalidPayloads')]
    public function testRejectsInvalidPayloads(mixed $payload, string $reason): void
    {
        $this->expectException(InvalidNotificationPayload::class);
        $this->expectExceptionMessage($reason);

        $this->mapper->mapToNotificationRequest($payload);
    }
}
