<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification;

use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Psr\Log\NullLogger;
use Shared\Condition\CompareCondition;
use Shared\Notification\Condition\ImportanceCondition;
use Shared\Notification\Importance;
use Shared\Notification\InvalidDestination;
use Shared\Notification\NotificationBuilder;
use Shared\Notification\NotificationDispatcher;
use Shared\Notification\Sender\ChimeTtsSender;
use Shared\Notification\Sender\Collection\DestinationSenderCollection;
use Shared\Notification\Sender\NotifyServiceSender;
use Stewart\Contracts\Entity\EntityId;
use Stringable;

final class NotificationDispatcherTest extends TestCase
{
    public function testSendsOnlyToDestinationsWhoseConditionsHold(): void
    {
        $recorder = new HaRecorder($this, [
            'input_select.zoli_notification_level' => 'low',
            'input_select.zsuzsi_notification_level' => 'high',
            'input_boolean.guest_mode' => 'on',
        ]);

        $this->dispatcher($recorder)->send(NotificationBuilder::create()
            ->withBody('Empty the vacuum bin')
            ->withImportance(Importance::Normal)
            ->withAppendedDestinations(
                NotifyServiceSender::createDestination('notify.mobile_app_zoli_phone')
                    ->withAppendedConditions(new ImportanceCondition(new EntityId('input_select.zoli_notification_level'))),
                NotifyServiceSender::createDestination('notify.mobile_app_zsuzsi_phone')
                    ->withAppendedConditions(new ImportanceCondition(new EntityId('input_select.zsuzsi_notification_level'))),
                ChimeTtsSender::createDestination('media_player.kitchen_speaker')
                    ->withAppendedConditions(CompareCondition::equals('input_boolean.guest_mode', 'off')),
            )
            ->buildNotification());

        self::assertSame(['notify.mobile_app_zoli_phone'], array_column($recorder->calls, 'service'));
    }

    public function testReturnsSentNotification(): void
    {
        $recorder = new HaRecorder($this);
        $notification = NotificationBuilder::create()->withBody('Hi')->withAppendedDestinations(NotifyServiceSender::createDestination('notify.notify'))->buildNotification();

        self::assertSame($notification, $this->dispatcher($recorder)->send($notification)->notification);
    }

    public function testRejectsTypeWithoutSenderBeforeSendingAny(): void
    {
        $recorder = new HaRecorder($this);
        $dispatcher = new NotificationDispatcher(
            DestinationSenderCollection::keyedByDestinationType([new NotifyServiceSender()]),
            $recorder->ha,
            $recorder->actionRouter(),
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
            self::assertSame([], $recorder->calls);
        }
    }

    public function testRejectsInvalidTargetBeforeSendingAny(): void
    {
        $recorder = new HaRecorder($this);

        try {
            $this->dispatcher($recorder)->send(NotificationBuilder::create()
                ->withBody('Hi')
                ->withAppendedDestinations(
                    ChimeTtsSender::createDestination('media_player.kitchen_speaker'),
                    NotifyServiceSender::createDestination('media_player.not_a_notify_service'),
                )
                ->buildNotification());
            self::fail('Expected InvalidDestination');
        } catch (InvalidDestination) {
            self::assertSame([], $recorder->calls);
        }
    }

    public function testDebugLogsDecisionsAndCalls(): void
    {
        $recorder = new HaRecorder($this, ['input_boolean.guest_mode' => 'on']);
        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $messages = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->messages[] = "{$level}: {$message}";
            }
        };

        new NotificationDispatcher(DestinationSenderCollection::keyedByDestinationType([new NotifyServiceSender()]), $recorder->ha, $recorder->actionRouter(), $logger)->send(NotificationBuilder::create()
            ->withBody('Hi')
            ->withDebug()
            ->withAppendedDestinations(
                NotifyServiceSender::createDestination('notify.notify'),
                NotifyServiceSender::createDestination('notify.tv')->withAppendedConditions(CompareCondition::equals('input_boolean.guest_mode', 'off')),
            )
            ->buildNotification());

        self::assertSame([
            'info: [NOTIFICATION] Sent',
            'info: [NOTIFICATION] Skipped destination, conditions not met',
        ], $logger->messages);
    }

    private function dispatcher(HaRecorder $recorder): NotificationDispatcher
    {
        return new NotificationDispatcher(DestinationSenderCollection::keyedByDestinationType([new NotifyServiceSender(), new ChimeTtsSender()]), $recorder->ha, $recorder->actionRouter(), new NullLogger());
    }
}
