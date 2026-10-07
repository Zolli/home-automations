<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification\Sender\Collection;

use PHPUnit\Framework\TestCase;
use Shared\Notification\DestinationType;
use Shared\Notification\Sender\Collection\DestinationSenderCollection;
use Shared\Notification\Sender\Collection\InvalidDestinationSenders;
use Shared\Notification\Sender\NotifyServiceSender;

final class DestinationSenderCollectionTest extends TestCase
{
    public function testFindsSenderByDestinationType(): void
    {
        $notify = new NotifyServiceSender();
        $senders = DestinationSenderCollection::keyedByDestinationType([$notify]);

        self::assertSame($notify, $senders->find(DestinationType::NotifyService));
        self::assertNull($senders->find(DestinationType::Tts));
    }

    public function testRejectsTwoSendersForSameType(): void
    {
        $this->expectException(InvalidDestinationSenders::class);

        DestinationSenderCollection::keyedByDestinationType([new NotifyServiceSender(), new NotifyServiceSender()]);
    }
}
