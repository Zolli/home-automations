<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification\Action;

use PHPUnit\Framework\TestCase;
use Shared\Notification\Action\ActionKey;
use Shared\Notification\Action\NotificationButton;
use Shared\Notification\NotificationId;

final class ActionKeyTest extends TestCase
{
    public function testRoundTrip(): void
    {
        $key = ActionKey::forButton(NotificationId::fromString('vacuum-bin'), new NotificationButton('DONE', 'Done'));

        self::assertSame('vacuum-bin:DONE', $key->toString());
        self::assertEquals($key, ActionKey::tryFromString($key->toString()));
    }

    public function testActionMayContainSeparator(): void
    {
        $key = ActionKey::tryFromString('n-1:a:b');

        self::assertNotNull($key);
        self::assertSame('n-1', $key->notificationId->value);
        self::assertSame('a:b', $key->action);
    }

    public function testForeignKeysAreNotParsed(): void
    {
        self::assertNull(ActionKey::tryFromString('DONE'));
        self::assertNull(ActionKey::tryFromString(':DONE'));
        self::assertNull(ActionKey::tryFromString('n-1:'));
        self::assertNull(ActionKey::tryFromString('n 1:DONE'));
    }
}
