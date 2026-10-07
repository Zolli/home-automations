<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification\Collection;

use PHPUnit\Framework\TestCase;
use Shared\Notification\Collection\DestinationCollection;
use Shared\Notification\Sender\ChimeTtsSender;
use Shared\Notification\Sender\NotifyServiceSender;

final class DestinationCollectionTest extends TestCase
{
    public function testAppendsDestinationsInOrder(): void
    {
        $phone = NotifyServiceSender::createDestination('notify.mobile_app_phone');
        $speaker = ChimeTtsSender::createDestination('media_player.kitchen');
        $tablet = NotifyServiceSender::createDestination('notify.mobile_app_tablet');

        $destinations = DestinationCollection::fromDestinations([$phone])->withAppendedDestinations($speaker, $tablet);

        self::assertSame([$phone, $speaker, $tablet], $destinations->listValues());
    }

    public function testAppendingKeepsOriginalUnchanged(): void
    {
        $original = DestinationCollection::fromDestinations([ChimeTtsSender::createDestination('media_player.kitchen')]);

        $original->withAppendedDestinations(ChimeTtsSender::createDestination('media_player.office'));

        self::assertCount(1, $original);
    }
}
