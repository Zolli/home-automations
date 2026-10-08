<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification\Sender;

use PHPUnit\Framework\TestCase;
use Shared\Notification\Exception\InvalidDestination;
use Shared\Notification\NotificationBuilder;
use Shared\Notification\Sender\ChimeTtsSender;

final class ChimeTtsSenderTest extends TestCase
{
    public function testBuildsOneSayCallForAllSpeakers(): void
    {
        $destination = ChimeTtsSender::createDestination(
            ['media_player.kitchen_speaker', 'media_player.living_room_speaker'],
            ['chime_path' => 'bells', 'volume_level' => 0.5],
        );
        $notification = NotificationBuilder::create()
            ->withBody('The laundry is done')
            ->withTitle('Ignored')
            ->withServiceData(['color' => 'ignored'])
            ->withAppendedDestinations($destination)
            ->buildNotification();

        $calls = new ChimeTtsSender()->buildServiceCalls($notification, $destination);

        self::assertCount(1, $calls);
        self::assertSame([
            'service' => 'chime_tts.say',
            'data' => ['message' => 'The laundry is done', 'chime_path' => 'bells', 'volume_level' => 0.5],
            'target' => ['entity_id' => ['media_player.kitchen_speaker', 'media_player.living_room_speaker']],
        ], $calls->listValues()[0]->toLogContext());
    }

    public function testOptionsCanOverrideSpokenMessage(): void
    {
        $destination = ChimeTtsSender::createDestination('media_player.kitchen_speaker', ['message' => 'Laundry!']);
        $notification = NotificationBuilder::create()->withBody('The laundry is done')->withAppendedDestinations($destination)->buildNotification();

        self::assertSame(['message' => 'Laundry!'], new ChimeTtsSender()->buildServiceCalls($notification, $destination)->listValues()[0]->data);
    }

    public function testRejectsTargetThatIsNotEntityId(): void
    {
        $this->expectException(InvalidDestination::class);

        new ChimeTtsSender()->validateDestination(ChimeTtsSender::createDestination('kitchen speaker'));
    }

    public function testRejectsTargetOutsideMediaPlayers(): void
    {
        $this->expectException(InvalidDestination::class);

        new ChimeTtsSender()->validateDestination(ChimeTtsSender::createDestination('light.kitchen'));
    }

    public function testSupportsTtsType(): void
    {
        self::assertSame('tts', new ChimeTtsSender()->getSupportedType()->value);
    }
}
