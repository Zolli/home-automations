<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification\Payload;

use App\Tests\ServiceContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Notification\Exception\InvalidNotificationPayload;
use Shared\Notification\Payload\NotificationPayloadSchema;

final class NotificationPayloadSchemaTest extends TestCase
{
    private const array DESTINATION = ['type' => 'notify_service', 'target' => 'notify.notify'];

    private NotificationPayloadSchema $schema;

    protected function setUp(): void
    {
        $this->schema = new ServiceContainer(NotificationPayloadSchema::class)->getService(NotificationPayloadSchema::class);
    }

    public function testAcceptsEmptyArraysAsOptionalObjects(): void
    {
        $this->expectNotToPerformAssertions();

        $this->schema->validatePayload([
            'message' => ['body' => 'Hi', 'additionalData' => ['data' => []]],
            'destinations' => [self::DESTINATION + ['options' => []]],
            'meta' => [],
        ]);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidPayloads(): iterable
    {
        yield 'unknown meta key' => [
            ['message' => ['body' => 'Hi'], 'destinations' => [self::DESTINATION], 'meta' => ['replyTopc' => 'a/b']],
            'meta.replyTopc is not allowed',
        ];
        yield 'buttons in additional data' => [
            ['message' => ['body' => 'Hi', 'additionalData' => ['data' => ['actions' => []]]], 'destinations' => [self::DESTINATION]],
            'message.additionalData.data.actions is not allowed',
        ];
        yield 'blank target in list' => [
            ['message' => ['body' => 'Hi'], 'destinations' => [['type' => 'tts', 'target' => ['media_player.kitchen', ' ']]]],
            'destinations[0].target[1] has an invalid format',
        ];
        yield 'list as meta' => [
            ['message' => ['body' => 'Hi'], 'destinations' => [self::DESTINATION], 'meta' => ['low']],
            'meta must have at most 0 items',
        ];
    }

    /** @param array<string, mixed> $payload */
    #[DataProvider('invalidPayloads')]
    public function testReportsPathOfInvalidValue(array $payload, string $violation): void
    {
        $this->expectException(InvalidNotificationPayload::class);
        $this->expectExceptionMessage($violation);

        $this->schema->validatePayload($payload);
    }

    public function testReportsEveryViolation(): void
    {
        $this->expectException(InvalidNotificationPayload::class);
        $this->expectExceptionMessage('message.body is required; destinations[0].target is required');

        $this->schema->validatePayload(['message' => ['title' => 'Hi'], 'destinations' => [['type' => 'tts']]]);
    }
}
