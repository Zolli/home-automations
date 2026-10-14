<?php

declare(strict_types=1);

namespace App\Tests\Shared\HouseMode\Strategy;

use App\Tests\Shared\HouseMode\FiringRecorder;
use App\Tests\Shared\HouseMode\ModeFixture;
use PHPUnit\Framework\TestCase;
use Shared\HouseMode\Strategy\StateStrategy;
use Shared\HouseMode\Strategy\StrategyTrigger;
use Stewart\Contracts\Entity\EntityId;
use Stewart\Contracts\Time\Duration;

final class StateStrategyTest extends TestCase
{
    private const string BED_SENSOR = 'binary_sensor.bed_occupied';

    private ModeFixture $fixture;

    private FiringRecorder $recorder;

    protected function setUp(): void
    {
        $this->fixture  = new ModeFixture();
        $this->recorder = new FiringRecorder();
        $this->fixture->harness->ha->pushState(self::BED_SENSOR, 'off');
    }

    public function testFiresAfterStateHeldForHoldDuration(): void
    {
        $this->attachBedPresence()->arm($this->recorder->recordStrategyFiring(...));

        $this->fixture->harness->ha->pushState(self::BED_SENSOR, 'on');
        $this->fixture->advanceTime(Duration::minutes(29));
        self::assertSame([], $this->recorder->firedStrategies);

        $this->fixture->advanceTime(Duration::minutes(1));

        self::assertSame(['bed_presence'], $this->recorder->firedStrategies);
    }

    public function testSkipsStateLeftWithinHoldDuration(): void
    {
        $this->attachBedPresence()->arm($this->recorder->recordStrategyFiring(...));

        $this->fixture->harness->ha->pushState(self::BED_SENSOR, 'on');
        $this->fixture->advanceTime(Duration::minutes(10));
        $this->fixture->harness->ha->pushState(self::BED_SENSOR, 'off');
        $this->fixture->advanceTime(Duration::minutes(30));

        self::assertSame([], $this->recorder->firedStrategies);
    }

    public function testStopsFiringWhenDisarmed(): void
    {
        $trigger = $this->attachBedPresence();
        $trigger->arm($this->recorder->recordStrategyFiring(...));

        $trigger->disarm();
        $this->fixture->harness->ha->pushState(self::BED_SENSOR, 'on');
        $this->fixture->advanceTime(Duration::minutes(30));

        self::assertSame([], $this->recorder->firedStrategies);
    }

    public function testDescribesEntityStateAndHold(): void
    {
        self::assertSame('binary_sensor.bed_occupied turns on for 30m', $this->attachBedPresence()->describe());
    }

    private function attachBedPresence(): StrategyTrigger
    {
        return new StateStrategy('bed_presence', new EntityId(self::BED_SENSOR), 'on', Duration::minutes(30))
            ->attach($this->fixture->createTransitionContext('activation'));
    }
}
