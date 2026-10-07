<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification;

use PHPUnit\Framework\TestCase;
use Shared\Condition\AllOf;
use Shared\Condition\CompareCondition;
use Shared\Notification\Destination;
use Shared\Notification\DestinationType;
use Shared\Notification\InvalidDestination;
use Shared\Notification\Sender\ChimeTtsSender;
use Shared\Notification\Sender\NotifyServiceSender;

final class DestinationTest extends TestCase
{
    public function testNormalizesTargets(): void
    {
        $destination = ChimeTtsSender::createDestination([' media_player.kitchen ', 'media_player.kitchen', '', 'media_player.bedroom']);

        self::assertSame(['media_player.kitchen', 'media_player.bedroom'], $destination->targets);
    }

    public function testRejectsEmptyTargets(): void
    {
        $this->expectException(InvalidDestination::class);

        new Destination(DestinationType::Tts, []);
    }

    public function testWithAppendedConditionsKeepsOriginalUnchanged(): void
    {
        $a = CompareCondition::equals('input_boolean.a', 'on');
        $b = CompareCondition::equals('input_boolean.b', 'on');
        $c = CompareCondition::lessThan('sensor.c', 3);
        $original = NotifyServiceSender::createDestination('notify.notify')->withAppendedConditions($a);
        $extended = $original->withAppendedConditions($b, $c);

        self::assertEquals(new AllOf($a), $original->condition);
        self::assertEquals(new AllOf($a, $b, $c), $extended->condition);
        self::assertSame($original->targets, $extended->targets);
    }
}
