<?php

declare(strict_types=1);

namespace App\Tests\Shared\HouseMode\Trigger;

use App\Tests\Shared\HouseMode\FiringRecorder;
use App\Tests\Shared\HouseMode\ModeFixture;
use PHPUnit\Framework\TestCase;
use Shared\HouseMode\TransitionContext;
use Shared\HouseMode\Trigger\StateTrigger;
use Stewart\Contracts\Entity\EntityId;
use Stewart\Contracts\Time\Duration;

final class StateTriggerTest extends TestCase
{
    private const string BED_SENSOR = 'binary_sensor.bed_occupied';

    private ModeFixture $fixture;

    private FiringRecorder $recorder;

    private TransitionContext $context;

    private StateTrigger $trigger;

    protected function setUp(): void
    {
        $this->fixture  = new ModeFixture();
        $this->recorder = new FiringRecorder();
        $this->context  = $this->fixture->createTransitionContext('activation');
        $this->trigger  = new StateTrigger('bed_presence', new EntityId(self::BED_SENSOR), 'on', Duration::minutes(30));
        $this->fixture->harness->ha->pushState(self::BED_SENSOR, 'off');
    }

    public function testFiresAfterStateHeldForHoldDuration(): void
    {
        $this->trigger->arm($this->context, $this->recorder->recordFiring(...));

        $this->fixture->harness->ha->pushState(self::BED_SENSOR, 'on');
        $this->fixture->advanceTime(Duration::minutes(29));
        self::assertSame([], $this->recorder->firedStrategies);

        $this->fixture->advanceTime(Duration::minutes(1));

        self::assertSame(['bed_presence'], $this->recorder->firedStrategies);
    }

    public function testSkipsStateLeftWithinHoldDuration(): void
    {
        $this->trigger->arm($this->context, $this->recorder->recordFiring(...));

        $this->fixture->harness->ha->pushState(self::BED_SENSOR, 'on');
        $this->fixture->advanceTime(Duration::minutes(10));
        $this->fixture->harness->ha->pushState(self::BED_SENSOR, 'off');
        $this->fixture->advanceTime(Duration::minutes(30));

        self::assertSame([], $this->recorder->firedStrategies);
    }

    public function testStopsFiringWhenDisarmed(): void
    {
        $this->trigger->arm($this->context, $this->recorder->recordFiring(...));

        $this->trigger->disarm();
        $this->fixture->harness->ha->pushState(self::BED_SENSOR, 'on');
        $this->fixture->advanceTime(Duration::minutes(30));

        self::assertSame([], $this->recorder->firedStrategies);
    }

    public function testDescribesEntityStateAndHold(): void
    {
        self::assertSame('binary_sensor.bed_occupied turns on for 30m', $this->trigger->describe());
    }
}
