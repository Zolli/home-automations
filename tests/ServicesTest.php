<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\TestCase;
use Shared\Alert\AlertMonitor;
use Shared\Alert\Config\AlertConfigMapper;
use Shared\Alert\Escalation\EscalationPolicyMapper;
use Shared\Alert\Mitigation\MitigationFactory;
use Shared\Alert\Rule\AlertRuleFactory;
use Shared\Alert\Rule\ThresholdRule;
use Shared\Condition\ConditionFactory;
use Shared\HouseMode\Config\ModeTransitionMapper;
use Shared\HouseMode\Payload\ModeEventPayloadMapper;
use Shared\HouseMode\TransitionMonitor;
use Shared\Notification\Condition\ImportanceCondition;
use Shared\Notification\NotificationBuilder;
use Shared\Notification\Notifier;
use Shared\Notification\Payload\NotificationActionPayloadMapper;
use Shared\Notification\Payload\NotificationPayloadMapper;
use Shared\Notification\Payload\NotificationPayloadSchema;
use Shared\Notification\Sender\ChimeTtsSender;
use Stewart\Testing\App\AppHarness;

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

        $harness = new AppHarness();
        $container->replaceSyntheticWithFakes($harness);

        self::assertInstanceOf(
            ImportanceCondition::class,
            $container
                ->getService(ConditionFactory::class)
                ->mapToCondition(['type' => 'importance', 'field' => 'input_select.level']),
        );

        $payload = [
            'message'      => ['body' => 'Hi'],
            'destinations' => [['type' => 'tts', 'target' => 'media_player.kitchen']],
        ];

        $container->getService(NotificationPayloadSchema::class)->validatePayload($payload);
        $container->getService(NotificationPayloadMapper::class)->mapToNotificationRequest($payload);
        $container->getService(NotificationActionPayloadMapper::class);

        $container->getService(Notifier::class)->send(
            NotificationBuilder::create()
                ->withBody('Hi')
                ->withAppendedDestinations(ChimeTtsSender::createDestination('media_player.kitchen'))
                ->buildNotification(),
        );

        self::assertCount(1, $harness->ha->calls);
        self::assertSame('chime_tts.say', $harness->ha->getLastCall()->getServiceName());
    }

    public function testWiresAlertConfigServices(): void
    {
        $container = new ServiceContainer(
            AlertRuleFactory::class,
            EscalationPolicyMapper::class,
            MitigationFactory::class,
            AlertConfigMapper::class,
            AlertMonitor::class,
        );
        $container->replaceSyntheticWithFakes(new AppHarness());
        $policies = $container->getService(EscalationPolicyMapper::class)->mapToPolicies([
            'critical' => ['steps' => [
                ['destinations' => [['type' => 'tts', 'target' => 'media_player.home']]],
                ['signal' => ['type' => 'light-effect', 'effect' => 'okay']],
            ]],
        ]);

        self::assertInstanceOf(
            ThresholdRule::class,
            $container
                ->getService(AlertRuleFactory::class)
                ->mapToRule(['type' => 'below', 'entity' => 'sensor.l1_voltage', 'threshold' => 180]),
        );
        self::assertNotNull($policies->find('critical'));
        self::assertTrue($container->getService(AlertConfigMapper::class)->mapToDefinitions([], [])->isEmpty());
        self::assertInstanceOf(AlertMonitor::class, $container->getService(AlertMonitor::class));
        self::assertCount(
            1,
            $container
                ->getService(MitigationFactory::class)
                ->mapToMitigations([['type' => 'turn-off', 'entity' => 'valve.main_water']]),
        );
    }

    public function testWiresHouseModeServices(): void
    {
        $container = new ServiceContainer(
            ModeTransitionMapper::class,
            TransitionMonitor::class,
            ModeEventPayloadMapper::class,
        );
        $container->replaceSyntheticWithFakes(new AppHarness());

        $transition = $container->getService(ModeTransitionMapper::class)->mapToTransition('day', [
            'strategies' => [
                ['name' => 'fixed_time', 'type' => 'fixed_time', 'time' => '07:00'],
                ['name' => 'covers_opened', 'type' => 'state', 'entity' => 'binary_sensor.covers'],
                ['name' => 'bright', 'type' => 'above', 'entity' => 'sensor.lux', 'threshold' => 800, 'max' => 1_000],
                ['name' => 'dark', 'type' => 'below', 'entity' => 'sensor.lux', 'threshold' => 50, 'max' => 1_000],
                ['name' => 'sunrise', 'type' => 'sun', 'event' => 'sunrise'],
            ],
        ]);

        self::assertCount(5, $transition->strategies);
        self::assertInstanceOf(TransitionMonitor::class, $container->getService(TransitionMonitor::class));
        self::assertInstanceOf(ModeEventPayloadMapper::class, $container->getService(ModeEventPayloadMapper::class));
    }
}
