<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification;

use PHPUnit\Framework\TestCase;
use Shared\Notification\Exception\InvalidNotification;
use Shared\Notification\NotificationId;

final class NotificationIdTest extends TestCase
{
    public function testGeneratesPrefixedRandomId(): void
    {
        self::assertMatchesRegularExpression('/^n-[0-9a-f]{12}$/', NotificationId::generate()->value);
    }

    public function testKeepsValidValue(): void
    {
        self::assertSame('vacuum-bin_2', NotificationId::fromString('vacuum-bin_2')->value);
    }

    public function testRejectsValueWithSeparator(): void
    {
        $this->expectException(InvalidNotification::class);

        NotificationId::fromString('vacuum:bin');
    }

    public function testRejectsEmptyValue(): void
    {
        $this->expectException(InvalidNotification::class);

        NotificationId::fromString('');
    }
}
