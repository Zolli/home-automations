<?php

declare(strict_types=1);

namespace App\Tests\Shared\Alert\Rule;

use App\Tests\Shared\Alert\HaFake;
use PHPUnit\Framework\TestCase;
use Shared\Alert\Reading\Collection\ReadingSourceCollection;
use Shared\Alert\Reading\ReadingFeed;
use Shared\Alert\Reading\ReadingSource;
use Shared\Alert\Rule\AlertRule;
use Shared\Alert\Rule\FaultWatcher;
use Shared\Alert\Rule\SilenceRule;
use Shared\Alert\Rule\StateMatchRule;
use Shared\Alert\Rule\ThresholdDirection;
use Shared\Alert\Rule\ThresholdRule;
use Stewart\Contracts\Time\Duration;

final class FaultWatcherTest extends TestCase
{
    private const string LEAK_SENSOR = 'binary_sensor.kitchen_leak';

    private const string DSMR_TOPIC = 'dsmr/reading/phase_voltage_l1';

    private HaFake $fake;
    private FaultListenerRecorder $listener;

    protected function setUp(): void
    {
        $this->fake = new HaFake($this);
        $this->listener = new FaultListenerRecorder();
    }

    public function testRaisesFaultActiveAtStart(): void
    {
        $this->fake->setState(self::LEAK_SENSOR, 'on');

        $this->watchLeak(Duration::zero())->startWatching();

        self::assertSame(['binary_sensor.kitchen_leak is on'], $this->listener->listReasons());
    }

    public function testRaisesOnceAndClearsOnRecovery(): void
    {
        $this->fake->setState(self::LEAK_SENSOR, 'off');
        $this->watchLeak(Duration::zero())->startWatching();

        $this->fake->setState(self::LEAK_SENSOR, 'on');
        $this->fake->setState(self::LEAK_SENSOR, 'on');
        $this->fake->setState(self::LEAK_SENSOR, 'off');

        self::assertSame(['binary_sensor.kitchen_leak is on', 'cleared'], $this->listener->listReasons());
    }

    public function testRaisesHeldFaultAfterHoldDuration(): void
    {
        $this->fake->setState(self::LEAK_SENSOR, 'off');
        $this->watchLeak(Duration::seconds(5))->startWatching();

        $this->fake->setState(self::LEAK_SENSOR, 'on');

        $this->fake->advanceBy(Duration::seconds(4));
        self::assertSame([], $this->listener->signals);

        $this->fake->advanceBy(Duration::seconds(1));

        self::assertSame(['binary_sensor.kitchen_leak is on'], $this->listener->listReasons());
    }

    public function testSkipsFaultRecoveredWithinHold(): void
    {
        $this->fake->setState(self::LEAK_SENSOR, 'off');
        $this->watchLeak(Duration::seconds(5))->startWatching();

        $this->fake->setState(self::LEAK_SENSOR, 'on');
        $this->fake->setState(self::LEAK_SENSOR, 'off');
        $this->fake->advanceBy(Duration::seconds(10));

        self::assertSame([], $this->listener->signals);
    }

    public function testKeepsFaultUntilHysteresisRecovers(): void
    {
        $this->fake->setState('sensor.relay_temperature', '70');
        $rule = new ThresholdRule(ReadingFixture::createEntitySources('sensor.relay_temperature'), ThresholdDirection::Above, 75.0, 5.0, Duration::zero());
        $this->createWatcher($rule)->startWatching();

        $this->fake->setState('sensor.relay_temperature', '76');
        $this->fake->setState('sensor.relay_temperature', '72');
        $this->fake->setState('sensor.relay_temperature', '70');

        self::assertSame(['sensor.relay_temperature is 76, above 75', 'cleared'], $this->listener->listReasons());
    }

    public function testStopWatchingCancelsPendingFault(): void
    {
        $this->fake->setState(self::LEAK_SENSOR, 'off');
        $watcher = $this->watchLeak(Duration::seconds(5));
        $watcher->startWatching();
        $this->fake->setState(self::LEAK_SENSOR, 'on');

        $watcher->stopWatching();

        self::assertSame(0, $this->fake->countPendingTasks());
        self::assertFalse($this->fake->isWatchingStates());
    }

    public function testRaisesSilentTopicWithoutAnyMessages(): void
    {
        $this->createWatcher($this->createSilenceRule())->startWatching();

        $this->fake->advanceBy(Duration::seconds(60));
        self::assertSame([], $this->listener->signals);

        $this->fake->advanceBy(Duration::seconds(6));
        self::assertSame(['dsmr/reading/phase_voltage_l1 sent nothing for 66 s'], $this->listener->listReasons());
    }

    public function testClearsSilenceOnNextMessage(): void
    {
        $this->createWatcher($this->createSilenceRule())->startWatching();
        $this->fake->publishMqtt(self::DSMR_TOPIC, '230');
        $this->fake->advanceBy(Duration::seconds(90));

        $this->fake->publishMqtt(self::DSMR_TOPIC, '230');

        self::assertSame(['dsmr/reading/phase_voltage_l1 sent nothing for 66 s', 'cleared'], $this->listener->listReasons());
    }

    public function testStopWatchingCancelsRecheck(): void
    {
        $watcher = $this->createWatcher($this->createSilenceRule());
        $watcher->startWatching();

        $watcher->stopWatching();

        self::assertSame(0, $this->fake->countPendingTasks());
        self::assertFalse($this->fake->isWatchingTopic(self::DSMR_TOPIC));
    }

    private function createSilenceRule(): SilenceRule
    {
        return new SilenceRule(ReadingSourceCollection::fromSources([ReadingSource::forTopic(self::DSMR_TOPIC)]), Duration::seconds(60), Duration::zero());
    }

    private function watchLeak(Duration $hold): FaultWatcher
    {
        return $this->createWatcher(new StateMatchRule(ReadingFixture::createEntitySources(self::LEAK_SENSOR), 'on', $hold));
    }

    private function createWatcher(AlertRule $rule): FaultWatcher
    {
        return new FaultWatcher($rule, new ReadingFeed($rule->listSources(), $this->fake->createMonitoringContext()), $this->fake->scheduler, $this->listener);
    }
}
