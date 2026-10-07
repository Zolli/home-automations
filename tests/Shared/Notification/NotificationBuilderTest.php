<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification;

use PHPUnit\Framework\TestCase;
use Shared\Condition\AllOf;
use Shared\Condition\CompareCondition;
use Shared\Notification\Action\NotificationButton;
use Shared\Notification\Condition\ImportanceCondition;
use Shared\Notification\DestinationType;
use Shared\Notification\Importance;
use Shared\Notification\InvalidMessage;
use Shared\Notification\InvalidNotification;
use Shared\Notification\NotificationBuilder;
use Shared\Notification\NotificationId;
use Shared\Notification\Sender\ChimeTtsSender;
use Shared\Notification\Sender\NotifyServiceSender;
use Stewart\Contracts\Entity\EntityId;

final class NotificationBuilderTest extends TestCase
{
    public function testBuildsVacuumNotification(): void
    {
        $notification = NotificationBuilder::create()
            ->withBody('Dusty is full of debris, please empty it')
            ->withTitle('Empty the vacuum bin')
            ->withServiceData(['notification_icon' => 'mdi:robot-vacuum-variant', 'color' => '#f47100'])
            ->withAppendedButtons(new NotificationButton('DONE', 'Done'), new NotificationButton('NEXT', 'Next time'))
            ->withImportance(Importance::Low)
            ->withDebug()
            ->withId(NotificationId::fromString('vacuum-bin'))
            ->withAppendedDestinations(
                NotifyServiceSender::createDestination('notify.mobile_app_zoli_phone')
                    ->withAppendedConditions(new ImportanceCondition(new EntityId('input_select.zoli_notification_level'))),
                ChimeTtsSender::createDestination('media_player.kitchen_speaker', ['chime_path' => 'bells'])
                    ->withAppendedConditions(CompareCondition::equals('input_boolean.guest_mode', 'off')),
            )
            ->buildNotification();

        self::assertSame('Empty the vacuum bin', $notification->message->title);
        self::assertSame('Dusty is full of debris, please empty it', $notification->message->body);
        self::assertSame(
            ['data' => ['notification_icon' => 'mdi:robot-vacuum-variant', 'color' => '#f47100']],
            $notification->message->additionalData,
        );
        self::assertEquals(
            [new NotificationButton('DONE', 'Done'), new NotificationButton('NEXT', 'Next time')],
            $notification->message->buttons->listValues(),
        );
        self::assertSame(Importance::Low, $notification->meta->importance);
        self::assertTrue($notification->meta->debug);
        self::assertSame('vacuum-bin', $notification->meta->id->value);

        [$phone, $speaker] = $notification->destinations->listValues();
        self::assertEquals(DestinationType::NotifyService, $phone->type);
        self::assertSame(['notify.mobile_app_zoli_phone'], $phone->targets);
        self::assertEquals(new AllOf(new ImportanceCondition(new EntityId('input_select.zoli_notification_level'))), $phone->condition);
        self::assertEquals(DestinationType::Tts, $speaker->type);
        self::assertSame(['chime_path' => 'bells'], $speaker->options);
    }

    public function testDefaults(): void
    {
        $notification = NotificationBuilder::create()->withBody('Hi')->withAppendedDestinations(NotifyServiceSender::createDestination('notify.notify'))->buildNotification();

        self::assertNull($notification->message->title);
        self::assertSame([], $notification->message->additionalData);
        self::assertFalse($notification->message->hasButtons());
        self::assertSame(Importance::Normal, $notification->meta->importance);
        self::assertFalse($notification->meta->debug);
        self::assertMatchesRegularExpression('/^n-[0-9a-f]{12}$/', $notification->meta->id->value);
    }

    public function testButtonsStayOutOfAdditionalData(): void
    {
        $notification = NotificationBuilder::create()
            ->withBody('Hi')
            ->withAppendedButtons(new NotificationButton('OPEN', 'Open', ['uri' => '/lovelace/0']))
            ->withServiceData(['tag' => 'hello'])
            ->withAppendedDestinations(NotifyServiceSender::createDestination('notify.notify'))
            ->buildNotification();

        self::assertSame(['data' => ['tag' => 'hello']], $notification->message->additionalData);
        self::assertEquals(
            [new NotificationButton('OPEN', 'Open', ['uri' => '/lovelace/0'])],
            $notification->message->buttons->listValues(),
        );
    }

    public function testMergesAdditionalDataWithServiceData(): void
    {
        $notification = NotificationBuilder::create()
            ->withBody('Hi')
            ->withServiceData(['color' => 'red'])
            ->withAdditionalData('data', ['tag' => 'hello'])
            ->withAdditionalData('target', ['device_1'])
            ->withAppendedDestinations(NotifyServiceSender::createDestination('notify.notify'))
            ->buildNotification();

        self::assertSame(['data' => ['color' => 'red', 'tag' => 'hello'], 'target' => ['device_1']], $notification->message->additionalData);
    }

    public function testReplacesAdditionalDataList(): void
    {
        $notification = NotificationBuilder::create()
            ->withBody('Hi')
            ->withAdditionalData('target', ['device_1', 'device_2'])
            ->withAdditionalData('target', ['device_3'])
            ->withAppendedDestinations(NotifyServiceSender::createDestination('notify.notify'))
            ->buildNotification();

        self::assertSame(['target' => ['device_3']], $notification->message->additionalData);
    }

    public function testRejectsEmptyBody(): void
    {
        $this->expectException(InvalidMessage::class);

        NotificationBuilder::create()->withBody(' ')->withTitle('Hi')->withAppendedDestinations(NotifyServiceSender::createDestination('notify.notify'))->buildNotification();
    }

    public function testRejectsRawActionsInServiceData(): void
    {
        $this->expectException(InvalidMessage::class);

        NotificationBuilder::create()
            ->withBody('Hi')
            ->withServiceData(['actions' => [['action' => 'OK', 'title' => 'Ok']]])
            ->withAppendedDestinations(NotifyServiceSender::createDestination('notify.notify'))
            ->buildNotification();
    }

    public function testRequiresBody(): void
    {
        $this->expectException(InvalidMessage::class);

        NotificationBuilder::create()->withAppendedDestinations(NotifyServiceSender::createDestination('notify.notify'))->buildNotification();
    }

    public function testRequiresDestination(): void
    {
        $this->expectException(InvalidNotification::class);

        NotificationBuilder::create()->withBody('Hi')->buildNotification();
    }
}
