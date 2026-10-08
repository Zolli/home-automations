<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification\Action;

use PHPUnit\Framework\TestCase;
use Shared\Notification\Action\NotificationButton;
use Shared\Notification\Exception\InvalidNotificationButton;

final class NotificationButtonTest extends TestCase
{
    public function testRejectsEmptyAction(): void
    {
        $this->expectException(InvalidNotificationButton::class);

        new NotificationButton(' ', 'Done');
    }

    public function testRejectsReservedOption(): void
    {
        $this->expectException(InvalidNotificationButton::class);

        new NotificationButton('DONE', 'Done', ['action' => 'OTHER']);
    }

    public function testUriButtonIsNotRoutable(): void
    {
        self::assertFalse(new NotificationButton(NotificationButton::URI_ACTION, 'Open', ['uri' => '/lovelace'])->isRoutable());
    }

    public function testActionButtonIsRoutable(): void
    {
        self::assertTrue(new NotificationButton('DONE', 'Done')->isRoutable());
    }
}
