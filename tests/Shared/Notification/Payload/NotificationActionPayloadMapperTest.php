<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification\Payload;

use PHPUnit\Framework\TestCase;
use Shared\Notification\Action\NotificationAction;
use Shared\Notification\NotificationId;
use Shared\Notification\Payload\NotificationActionPayloadMapper;

final class NotificationActionPayloadMapperTest extends TestCase
{
    public function testMapsAllFields(): void
    {
        $action = new NotificationAction(NotificationId::fromString('vacuum-bin'), 'DONE', ['reply_text' => 'emptied'], 'user-1');

        self::assertSame(
            ['id' => 'vacuum-bin', 'action' => 'DONE', 'replyText' => 'emptied', 'userId' => 'user-1'],
            new NotificationActionPayloadMapper()->mapToPayload($action),
        );
    }

    public function testOmitsMissingReplyAndUser(): void
    {
        $action = new NotificationAction(NotificationId::fromString('vacuum-bin'), 'NEXT');

        self::assertSame(['id' => 'vacuum-bin', 'action' => 'NEXT'], new NotificationActionPayloadMapper()->mapToPayload($action));
    }
}
