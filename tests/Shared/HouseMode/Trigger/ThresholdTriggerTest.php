<?php

declare(strict_types=1);

namespace App\Tests\Shared\HouseMode\Trigger;

use App\Tests\Shared\HouseMode\FiringRecorder;
use App\Tests\Shared\HouseMode\ModeFixture;
use PHPUnit\Framework\TestCase;
use Shared\HouseMode\TransitionContext;
use Shared\HouseMode\Trigger\ThresholdRange;
use Shared\HouseMode\Trigger\ThresholdTrigger;
use Shared\Threshold\ThresholdDirection;
use Stewart\Contracts\Entity\EntityId;
use Stewart\Contracts\Time\Duration;

final class ThresholdTriggerTest extends TestCase
{
    private const string LUX_SENSOR    = 'sensor.outdoor_lux';
    private const string THRESHOLD_KEY = 'day_light_level_threshold';

    private ModeFixture $fixture;

    private FiringRecorder $recorder;

    private TransitionContext $context;

    protected function setUp(): void
    {
        $this->fixture  = new ModeFixture();
        $this->recorder = new FiringRecorder();
        $this->context  = $this->fixture->createTransitionContext('day');
        $this->fixture->harness->ha->pushState(self::LUX_SENSOR, '100');
    }

    public function testAboveFiresAfterHeldAboveThreshold(): void
    {
        $this->attachAndArm($this->createLightLevel(ThresholdDirection::Above, Duration::minutes(5)));

        $this->fixture->harness->ha->pushState(self::LUX_SENSOR, '900');
        $this->fixture->advanceTime(Duration::minutes(4));
        self::assertSame([], $this->recorder->firedStrategies);

        $this->fixture->advanceTime(Duration::minutes(1));

        self::assertSame(['light_level'], $this->recorder->firedStrategies);
    }

    public function testBelowFiresWhenFallingBelowThreshold(): void
    {
        $this->fixture->harness->ha->pushState(self::LUX_SENSOR, '1000');
        $this->attachAndArm($this->createLightLevel(ThresholdDirection::Below));

        $this->fixture->harness->ha->pushState(self::LUX_SENSOR, '900');
        $this->fixture->harness->ha->pushState(self::LUX_SENSOR, '700');
        $this->fixture->harness->ha->pushState(self::LUX_SENSOR, '600');

        self::assertSame(['light_level'], $this->recorder->firedStrategies);
    }

    public function testRewatchesWhenThresholdChanged(): void
    {
        $trigger = $this->createLightLevel(ThresholdDirection::Above);
        $this->attachAndArm($trigger);

        $this->fixture->changeNumber(self::THRESHOLD_KEY, 300.0);
        $this->fixture->harness->ha->pushState(self::LUX_SENSOR, '400');

        self::assertSame(['light_level'], $this->recorder->firedStrategies);
        self::assertSame('sensor.outdoor_lux above 300', $trigger->describe());
    }

    public function testSeedsThresholdWhenHomeAssistantHasNone(): void
    {
        $this->fixture->restoreExposedValue(self::THRESHOLD_KEY, null);

        $this->createLightLevel(ThresholdDirection::Above)->attach($this->context);

        self::assertSame(800.0, $this->fixture->harness->entities->requireNumber(self::THRESHOLD_KEY)->getValue());
    }

    public function testUsesRestoredThreshold(): void
    {
        $this->fixture->restoreExposedValue(self::THRESHOLD_KEY, 2000);
        $trigger = $this->createLightLevel(ThresholdDirection::Above);

        $trigger->attach($this->context);

        self::assertSame('sensor.outdoor_lux above 2000', $trigger->describe());
    }

    private function createLightLevel(ThresholdDirection $direction, ?Duration $holdDuration = null): ThresholdTrigger
    {
        return new ThresholdTrigger(
            'light_level',
            new EntityId(self::LUX_SENSOR),
            $direction,
            800.0,
            new ThresholdRange(0.0, 100_000.0, unit: 'lx'),
            holdDuration: $holdDuration,
        );
    }

    private function attachAndArm(ThresholdTrigger $trigger): void
    {
        $trigger->attach($this->context);
        $trigger->arm($this->context, $this->recorder->recordFiring(...));
    }
}
