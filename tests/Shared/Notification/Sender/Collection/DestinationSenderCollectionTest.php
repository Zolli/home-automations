<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification\Sender\Collection;

use PHPUnit\Framework\TestCase;
use Shared\Notification\DestinationType;
use Shared\Notification\Exception\InvalidDestinationSenders;
use Shared\Notification\Sender\Collection\DestinationSenderCollection;
use Shared\Notification\Sender\NotifyActionSender;

final class DestinationSenderCollectionTest extends TestCase
{
    public function testFindsSenderByDestinationType(): void
    {
        $notify  = new NotifyActionSender();
        $senders = DestinationSenderCollection::keyedByDestinationType([$notify]);

        self::assertSame($notify, $senders->find(DestinationType::NotifyAction));
        self::assertNull($senders->find(DestinationType::Tts));
    }

    public function testRejectsTwoSendersForSameType(): void
    {
        $this->expectException(InvalidDestinationSenders::class);

        DestinationSenderCollection::keyedByDestinationType([new NotifyActionSender(), new NotifyActionSender()]);
    }
}
