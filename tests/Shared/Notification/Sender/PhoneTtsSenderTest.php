<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification\Sender;

use PHPUnit\Framework\TestCase;
use Shared\Notification\Exception\InvalidDestination;
use Shared\Notification\NotificationBuilder;
use Shared\Notification\Sender\PhoneTtsSender;

final class PhoneTtsSenderTest extends TestCase
{
    public function testSpeaksBodyOnAlarmStreamPerPhone(): void
    {
        $destination = PhoneTtsSender::createDestination(['notify.mobile_app_zoli_phone', 'notify.mobile_app_other_phone']);
        $notification = NotificationBuilder::create()
            ->withTitle('SMOKE ALARM')
            ->withBody('Smoke alarm in the Kitchen')
            ->withAppendedDestinations($destination)
            ->buildNotification();

        $calls = new PhoneTtsSender()->buildServiceCalls($notification, $destination)->listValues();

        self::assertCount(2, $calls);
        self::assertSame([
            'service' => 'notify.mobile_app_zoli_phone',
            'data' => ['message' => 'TTS', 'data' => ['tts_text' => 'Smoke alarm in the Kitchen', 'media_stream' => 'alarm_stream_max']],
        ], $calls[0]->toLogContext());
        self::assertSame('mobile_app_other_phone', $calls[1]->service);
    }

    public function testOptionsOverrideMediaStream(): void
    {
        $destination = PhoneTtsSender::createDestination('notify.mobile_app_zoli_phone', ['media_stream' => 'alarm_stream']);
        $notification = NotificationBuilder::create()->withBody('Hi')->withAppendedDestinations($destination)->buildNotification();

        $data = new PhoneTtsSender()->buildServiceCalls($notification, $destination)->listValues()[0]->data;

        self::assertSame(['tts_text' => 'Hi', 'media_stream' => 'alarm_stream'], $data['data'] ?? null);
    }

    public function testRejectsTargetOutsideNotify(): void
    {
        $this->expectException(InvalidDestination::class);

        new PhoneTtsSender()->validateDestination(PhoneTtsSender::createDestination('media_player.kitchen'));
    }

    public function testSupportsPhoneTtsType(): void
    {
        self::assertSame('phone_tts', new PhoneTtsSender()->getSupportedType()->value);
    }
}
