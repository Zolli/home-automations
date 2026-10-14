<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification;

use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shared\Condition\CompareCondition;
use Shared\Notification\Condition\ImportanceCondition;
use Shared\Notification\Exception\InvalidDestination;
use Shared\Notification\Importance;
use Shared\Notification\NotificationBuilder;
use Shared\Notification\NotificationDispatcher;
use Shared\Notification\Sender\ChimeTtsSender;
use Shared\Notification\Sender\Collection\DestinationSenderCollection;
use Shared\Notification\Sender\NotifyServiceSender;
use Stewart\Contracts\Entity\EntityId;
use Stewart\Testing\Logging\RecordingLogger;

final class NotificationDispatcherTest extends TestCase
{
    public function testSendsOnlyToDestinationsWhoseConditionsHold(): void
    {
        $fixture = new NotificationFixture();
        $fixture->ha->seedState('input_select.zoli_notification_level', 'low');
        $fixture->ha->seedState('input_select.zsuzsi_notification_level', 'high');
        $fixture->ha->seedState('input_boolean.guest_mode', 'on');

        $this->dispatcher($fixture)->send(NotificationBuilder::create()
            ->withBody('Empty the vacuum bin')
            ->withImportance(Importance::Normal)
            ->withAppendedDestinations(
                NotifyServiceSender::createDestination('notify.mobile_app_zoli_phone')
                    ->withAppendedConditions(
                        new ImportanceCondition(new EntityId('input_select.zoli_notification_level')),
                    ),
                NotifyServiceSender::createDestination('notify.mobile_app_zsuzsi_phone')
                    ->withAppendedConditions(
                        new ImportanceCondition(new EntityId('input_select.zsuzsi_notification_level')),
                    ),
                ChimeTtsSender::createDestination('media_player.kitchen_speaker')
                    ->withAppendedConditions(CompareCondition::equals('input_boolean.guest_mode', 'off')),
            )
            ->buildNotification());

        self::assertSame(['notify.mobile_app_zoli_phone'], $fixture->listCalledServices());
    }

    public function testReturnsSentNotification(): void
    {
        $fixture      = new NotificationFixture();
        $notification = NotificationBuilder::create()
            ->withBody('Hi')
            ->withAppendedDestinations(NotifyServiceSender::createDestination('notify.notify'))
            ->buildNotification();

        self::assertSame($notification, $this->dispatcher($fixture)->send($notification)->notification);
    }

    public function testRejectsTypeWithoutSenderBeforeSendingAny(): void
    {
        $fixture    = new NotificationFixture();
        $dispatcher = new NotificationDispatcher(
            DestinationSenderCollection::keyedByDestinationType([new NotifyServiceSender()]),
            $fixture->ha,
            $fixture->createActionRouter(),
            new NullLogger(),
        );

        try {
            $dispatcher->send(NotificationBuilder::create()
                ->withBody('Hi')
                ->withAppendedDestinations(
                    NotifyServiceSender::createDestination('notify.notify'),
                    ChimeTtsSender::createDestination('media_player.kitchen_speaker'),
                )
                ->buildNotification());
            self::fail('Expected InvalidDestination');
        } catch (InvalidDestination) {
            self::assertTrue($fixture->ha->calls->isEmpty());
        }
    }

    public function testRejectsInvalidTargetBeforeSendingAny(): void
    {
        $fixture = new NotificationFixture();

        try {
            $this->dispatcher($fixture)->send(NotificationBuilder::create()
                ->withBody('Hi')
                ->withAppendedDestinations(
                    ChimeTtsSender::createDestination('media_player.kitchen_speaker'),
                    NotifyServiceSender::createDestination('media_player.not_a_notify_service'),
                )
                ->buildNotification());
            self::fail('Expected InvalidDestination');
        } catch (InvalidDestination) {
            self::assertTrue($fixture->ha->calls->isEmpty());
        }
    }

    public function testDebugLogsDecisionsAndCalls(): void
    {
        $fixture = new NotificationFixture();
        $fixture->ha->seedState('input_boolean.guest_mode', 'on');
        $logger     = new RecordingLogger();
        $dispatcher = new NotificationDispatcher(
            DestinationSenderCollection::keyedByDestinationType([new NotifyServiceSender()]),
            $fixture->ha,
            $fixture->createActionRouter(),
            $logger,
        );

        $dispatcher->send(NotificationBuilder::create()
            ->withBody('Hi')
            ->withDebug()
            ->withAppendedDestinations(
                NotifyServiceSender::createDestination('notify.notify'),
                NotifyServiceSender::createDestination('notify.tv')
                    ->withAppendedConditions(CompareCondition::equals('input_boolean.guest_mode', 'off')),
            )
            ->buildNotification());

        self::assertSame([
            '[NOTIFICATION] Sent',
            '[NOTIFICATION] Skipped destination, conditions not met',
        ], $logger->listMessagesAt('info'));
    }

    private function dispatcher(NotificationFixture $fixture): NotificationDispatcher
    {
        return new NotificationDispatcher(
            DestinationSenderCollection::keyedByDestinationType([new NotifyServiceSender(), new ChimeTtsSender()]),
            $fixture->ha,
            $fixture->createActionRouter(),
            new NullLogger(),
        );
    }
}
