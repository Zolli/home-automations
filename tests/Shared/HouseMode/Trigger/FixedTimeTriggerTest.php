<?php

declare(strict_types=1);

namespace App\Tests\Shared\HouseMode\Trigger;

use App\Tests\Shared\HouseMode\FiringRecorder;
use App\Tests\Shared\HouseMode\ModeFixture;
use PHPUnit\Framework\TestCase;
use Shared\HouseMode\TransitionContext;
use Shared\HouseMode\Trigger\FixedTimeTrigger;
use Stewart\Contracts\Schedule\TimeOfDay;
use Stewart\Contracts\Time\Duration;

final class FixedTimeTriggerTest extends TestCase
{
    private const string TIME_KEY = 'day_fixed_time_time';

    private ModeFixture $fixture;

    private FiringRecorder $recorder;

    private TransitionContext $context;

    private FixedTimeTrigger $trigger;

    protected function setUp(): void
    {
        $this->fixture  = new ModeFixture('2026-10-14 12:00');
        $this->recorder = new FiringRecorder();
        $this->context  = $this->fixture->createTransitionContext('day');
        $this->trigger  = new FixedTimeTrigger('fixed_time', TimeOfDay::parse('13:00'));
    }

    public function testFiresDailyAtConfiguredTime(): void
    {
        $this->attachAndArm();

        $this->fixture->advanceTime(Duration::minutes(59));
        self::assertSame([], $this->recorder->firedStrategies);

        $this->fixture->advanceTime(Duration::minutes(1));
        $this->fixture->advanceTime(Duration::hours(24));

        self::assertSame(['fixed_time', 'fixed_time'], $this->recorder->firedStrategies);
    }

    public function testSeedsTimeWhenHomeAssistantHasNone(): void
    {
        $this->fixture->restoreExposedValue(self::TIME_KEY, null);

        $this->trigger->attach($this->context);

        $exposedTime = $this->fixture->harness->entities->requireTime(self::TIME_KEY);

        self::assertSame('13:00:00', $exposedTime->getValue()?->format());
    }

    public function testSkipsSeedingBeforeEntityExists(): void
    {
        $this->trigger->attach($this->context);

        self::assertTrue($this->fixture->harness->entities->requireTime(self::TIME_KEY)->changes->isEmpty());
    }

    public function testFiresAtRestoredTime(): void
    {
        $this->fixture->restoreExposedValue(self::TIME_KEY, '14:00:00');
        $this->attachAndArm();

        $this->fixture->advanceTime(Duration::minutes(90));
        self::assertSame([], $this->recorder->firedStrategies);

        $this->fixture->advanceTime(Duration::minutes(30));

        self::assertSame(['fixed_time'], $this->recorder->firedStrategies);
        self::assertSame('daily at 14:00:00', $this->trigger->describe());
    }

    public function testReschedulesWhenTimeChanged(): void
    {
        $this->attachAndArm();

        $this->fixture->changeTime(self::TIME_KEY, '12:30');
        $this->fixture->advanceTime(Duration::minutes(30));

        self::assertSame(['fixed_time'], $this->recorder->firedStrategies);
    }

    public function testKeepsTimeChangedWhileDisarmed(): void
    {
        $this->trigger->attach($this->context);

        $this->fixture->changeTime(self::TIME_KEY, '12:30');
        $this->trigger->arm($this->context, $this->recorder->recordFiring(...));
        $this->fixture->advanceTime(Duration::minutes(30));

        self::assertSame(['fixed_time'], $this->recorder->firedStrategies);
    }

    public function testStopsFiringWhenDisarmed(): void
    {
        $this->attachAndArm();

        $this->trigger->disarm();
        $this->fixture->changeTime(self::TIME_KEY, '12:30');
        $this->fixture->advanceTime(Duration::hours(2));

        self::assertSame([], $this->recorder->firedStrategies);
    }

    private function attachAndArm(): void
    {
        $this->trigger->attach($this->context);
        $this->trigger->arm($this->context, $this->recorder->recordFiring(...));
    }
}
