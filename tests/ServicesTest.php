<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shared\Condition\ConditionFactory;
use Shared\Notification\Condition\ImportanceCondition;
use Shared\Notification\NotificationBuilder;
use Shared\Notification\Notifier;
use Shared\Notification\Payload\NotificationActionPayloadMapper;
use Shared\Notification\Payload\NotificationPayloadMapper;
use Shared\Notification\Payload\NotificationPayloadSchema;
use Shared\Notification\Sender\ChimeTtsSender;
use Stewart\Contracts\HaContext;
use Stewart\Contracts\Schedule\Scheduler;

final class ServicesTest extends TestCase
{
    public function testWiresNotifier(): void
    {
        $container = new ServiceContainer(
            Notifier::class,
            ConditionFactory::class,
            NotificationPayloadSchema::class,
            NotificationPayloadMapper::class,
            NotificationActionPayloadMapper::class,
        );

        $ha = $this->createMock(HaContext::class);
        $ha->expects($this->once())->method('callService')->with('chime_tts', 'say');
        $container->replaceSynthetic(HaContext::class, $ha);
        $container->replaceSynthetic(Scheduler::class, $this->createStub(Scheduler::class));
        $container->replaceSynthetic(LoggerInterface::class, $this->createStub(LoggerInterface::class));

        self::assertInstanceOf(
            ImportanceCondition::class,
            $container->getService(ConditionFactory::class)->mapToCondition(['type' => 'importance', 'field' => 'input_select.level']),
        );

        $container->getService(NotificationPayloadSchema::class)->validatePayload(['message' => ['body' => 'Hi'], 'destinations' => [['type' => 'tts', 'target' => 'media_player.kitchen']]]);
        $container->getService(NotificationPayloadMapper::class)->mapToNotificationRequest(['message' => ['body' => 'Hi'], 'destinations' => [['type' => 'tts', 'target' => 'media_player.kitchen']]]);
        $container->getService(NotificationActionPayloadMapper::class);

        $container->getService(Notifier::class)->send(NotificationBuilder::create()->withBody('Hi')->withAppendedDestinations(ChimeTtsSender::createDestination('media_player.kitchen'))->buildNotification());
    }
}
