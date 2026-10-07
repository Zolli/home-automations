<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification\Sender;

use PHPUnit\Framework\TestCase;
use Shared\Notification\Action\NotificationButton;
use Shared\Notification\Destination;
use Shared\Notification\DestinationType;
use Shared\Notification\InvalidDestination;
use Shared\Notification\NotificationBuilder;
use Shared\Notification\NotificationId;
use Shared\Notification\Sender\NotifyServiceSender;
use Shared\Notification\Sender\ServiceCall;

final class NotifyServiceSenderTest extends TestCase
{
    public function testBuildsOneNotifyCallPerTarget(): void
    {
        $destination = new Destination(DestinationType::NotifyService, ['notify.mobile_app_zoli_phone', 'notify.persistent_notification']);
        $notification = NotificationBuilder::create()
            ->withBody('Dusty is full')
            ->withTitle('Empty the vacuum bin')
            ->withServiceData(['color' => '#f47100'])
            ->withAppendedButtons(new NotificationButton('DONE', 'Done'))
            ->withAppendedButtons(new NotificationButton(NotificationButton::URI_ACTION, 'Open', ['uri' => '/lovelace/vacuum']))
            ->withId(NotificationId::fromString('vacuum-bin'))
            ->withAppendedDestinations($destination)
            ->buildNotification();

        $data = [
            'message' => 'Dusty is full',
            'title' => 'Empty the vacuum bin',
            'data' => [
                'color' => '#f47100',
                'actions' => [
                    ['action' => 'vacuum-bin:DONE', 'title' => 'Done'],
                    ['action' => 'URI', 'title' => 'Open', 'uri' => '/lovelace/vacuum'],
                ],
                'tag' => 'vacuum-bin',
            ],
        ];

        self::assertEquals([
            new ServiceCall('notify', 'mobile_app_zoli_phone', $data),
            new ServiceCall('notify', 'persistent_notification', $data),
        ], new NotifyServiceSender()->buildServiceCalls($notification, $destination)->listValues());
    }

    public function testDestinationOptionsWin(): void
    {
        $destination = NotifyServiceSender::createDestination('notify.persistent_notification', ['data' => ['notification_id' => 'vacuum']]);
        $notification = NotificationBuilder::create()->withBody('Dusty is full')->withServiceData(['color' => 'red'])->withAppendedDestinations($destination)->buildNotification();

        self::assertSame(
            ['message' => 'Dusty is full', 'data' => ['color' => 'red', 'notification_id' => 'vacuum']],
            new NotifyServiceSender()->buildServiceCalls($notification, $destination)->listValues()[0]->data,
        );
    }

    public function testDestinationOptionsReplaceLists(): void
    {
        $destination = NotifyServiceSender::createDestination('notify.notify', ['target' => ['device_3']]);
        $notification = NotificationBuilder::create()->withBody('Hi')->withAdditionalData('target', ['device_1', 'device_2'])->withAppendedDestinations($destination)->buildNotification();

        self::assertSame(
            ['message' => 'Hi', 'target' => ['device_3']],
            new NotifyServiceSender()->buildServiceCalls($notification, $destination)->listValues()[0]->data,
        );
    }

    public function testKeepsExplicitTag(): void
    {
        $destination = NotifyServiceSender::createDestination('notify.mobile_app_zoli_phone');
        $notification = NotificationBuilder::create()->withBody('Hi')->withServiceData(['tag' => 'mine'])->withAppendedButtons(new NotificationButton('OK', 'Ok'))->withAppendedDestinations($destination)->buildNotification();

        $data = new NotifyServiceSender()->buildServiceCalls($notification, $destination)->listValues()[0]->data['data'] ?? null;

        self::assertIsArray($data);
        self::assertSame('mine', $data['tag']);
    }

    public function testRejectsNonNotifyTarget(): void
    {
        $this->expectException(InvalidDestination::class);

        new NotifyServiceSender()->validateDestination(NotifyServiceSender::createDestination('media_player.kitchen'));
    }

    public function testValidatesEveryTarget(): void
    {
        $this->expectException(InvalidDestination::class);

        new NotifyServiceSender()->validateDestination(new Destination(DestinationType::NotifyService, ['notify.mobile_app_zoli_phone', 'notify.']));
    }

    public function testSupportsNotifyServiceType(): void
    {
        self::assertSame('notify_service', new NotifyServiceSender()->getSupportedType()->value);
    }
}
