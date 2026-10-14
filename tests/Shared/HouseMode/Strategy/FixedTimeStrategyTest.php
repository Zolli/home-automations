<?php

declare(strict_types=1);

namespace App\Tests\Shared\HouseMode\Strategy;

use App\Tests\Shared\HouseMode\FiringRecorder;
use App\Tests\Shared\HouseMode\ModeFixture;
use PHPUnit\Framework\TestCase;
use Shared\HouseMode\Strategy\FixedTimeStrategy;
use Shared\HouseMode\Strategy\StrategyTrigger;
use Stewart\Contracts\Schedule\TimeOfDay;
use Stewart\Contracts\Time\Duration;

final class FixedTimeStrategyTest extends TestCase
{
    private const string TIME_KEY = 'day_fixed_time_time';

    private ModeFixture $fixture;

    private FiringRecorder $recorder;

    protected function setUp(): void
    {
        $this->fixture  = new ModeFixture('2026-10-14 12:00');
        $this->recorder = new FiringRecorder();
    }

    public function testFiresDailyAtConfiguredTime(): void
    {
        $this->attachAt('13:00')->arm($this->recorder->recordStrategyFiring(...));

        $this->fixture->advanceTime(Duration::minutes(59));
        self::assertSame([], $this->recorder->firedStrategies);

        $this->fixture->advanceTime(Duration::minutes(1));
        $this->fixture->advanceTime(Duration::hours(24));

        self::assertSame(['fixed_time', 'fixed_time'], $this->recorder->firedStrategies);
    }

    public function testSeedsTimeWhenHomeAssistantHasNone(): void
    {
        $this->fixture->restoreExposedValue(self::TIME_KEY, null);

        $this->attachAt('13:00');

        $exposedTime = $this->fixture->harness->entities->requireTime(self::TIME_KEY);

        self::assertSame('13:00:00', $exposedTime->getValue()?->format());
    }

    public function testSkipsSeedingBeforeEntityExists(): void
    {
        $this->attachAt('13:00');

        self::assertTrue($this->fixture->harness->entities->requireTime(self::TIME_KEY)->changes->isEmpty());
    }

    public function testFiresAtRestoredTime(): void
    {
        $this->fixture->restoreExposedValue(self::TIME_KEY, '14:00:00');
        $trigger = $this->attachAt('13:00');
        $trigger->arm($this->recorder->recordStrategyFiring(...));

        $this->fixture->advanceTime(Duration::minutes(90));
        self::assertSame([], $this->recorder->firedStrategies);

        $this->fixture->advanceTime(Duration::minutes(30));

        self::assertSame(['fixed_time'], $this->recorder->firedStrategies);
        self::assertSame('daily at 14:00:00', $trigger->describe());
    }

    public function testReschedulesWhenTimeChanged(): void
    {
        $this->attachAt('13:00')->arm($this->recorder->recordStrategyFiring(...));

        $this->fixture->changeTime(self::TIME_KEY, '12:30');
        $this->fixture->advanceTime(Duration::minutes(30));

        self::assertSame(['fixed_time'], $this->recorder->firedStrategies);
    }

    public function testKeepsTimeChangedWhileDisarmed(): void
    {
        $trigger = $this->attachAt('13:00');

        $this->fixture->changeTime(self::TIME_KEY, '12:30');
        $trigger->arm($this->recorder->recordStrategyFiring(...));
        $this->fixture->advanceTime(Duration::minutes(30));

        self::assertSame(['fixed_time'], $this->recorder->firedStrategies);
    }

    public function testStopsFiringWhenDisarmed(): void
    {
        $trigger = $this->attachAt('13:00');
        $trigger->arm($this->recorder->recordStrategyFiring(...));

        $trigger->disarm();
        $this->fixture->advanceTime(Duration::hours(2));

        self::assertSame([], $this->recorder->firedStrategies);
    }

    private function attachAt(string $time): StrategyTrigger
    {
        return new FixedTimeStrategy('fixed_time', TimeOfDay::parse($time))
            ->attach($this->fixture->createTransitionContext('day'));
    }
}
