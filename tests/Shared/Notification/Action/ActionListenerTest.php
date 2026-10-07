<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification\Action;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shared\Notification\Action\ActionListener;
use Shared\Notification\Action\NotificationAction;
use Shared\Notification\NotificationId;

final class ActionListenerTest extends TestCase
{
    public function testStopReleasesOnceAndDropsCallbacks(): void
    {
        $released = 0;
        $listener = new ActionListener(NotificationId::fromString('vacuum-bin'), new NullLogger(), function () use (&$released): void {
            $released++;
        });
        $listener->onAnyAction(fn() => self::fail('Callbacks were dropped'));

        $listener->stopListening();
        $listener->stopListening();
        $listener->dispatchAction(new NotificationAction(NotificationId::fromString('vacuum-bin'), 'DONE'));

        self::assertSame(1, $released);
        self::assertFalse($listener->isListening());
    }

    public function testStoppedListenerIgnoresNewCallbacks(): void
    {
        $listener = new ActionListener(NotificationId::fromString('vacuum-bin'), new NullLogger(), fn() => null, listening: false);
        $listener->onAction('DONE', fn() => self::fail('Callback was registered'));

        $listener->dispatchAction(new NotificationAction(NotificationId::fromString('vacuum-bin'), 'DONE'));

        self::assertFalse($listener->isListening());
    }
}
