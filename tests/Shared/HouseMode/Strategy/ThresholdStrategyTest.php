<?php

declare(strict_types=1);

namespace App\Tests\Shared\HouseMode\Strategy;

use App\Tests\Shared\HouseMode\FiringRecorder;
use App\Tests\Shared\HouseMode\ModeFixture;
use PHPUnit\Framework\TestCase;
use Shared\HouseMode\Strategy\StrategyTrigger;
use Shared\HouseMode\Strategy\ThresholdRange;
use Shared\HouseMode\Strategy\ThresholdStrategy;
use Shared\Threshold\ThresholdDirection;
use Stewart\Contracts\Entity\EntityId;
use Stewart\Contracts\Time\Duration;

final class ThresholdStrategyTest extends TestCase
{
    private const string LUX_SENSOR    = 'sensor.outdoor_lux';
    private const string THRESHOLD_KEY = 'day_light_level_threshold';

    private ModeFixture $fixture;

    private FiringRecorder $recorder;

    protected function setUp(): void
    {
        $this->fixture  = new ModeFixture();
        $this->recorder = new FiringRecorder();
        $this->fixture->harness->ha->pushState(self::LUX_SENSOR, '100');
    }

    public function testAboveFiresAfterHeldAboveThreshold(): void
    {
        $this->attachLightLevel(ThresholdDirection::Above, Duration::minutes(5))
            ->arm($this->recorder->recordStrategyFiring(...));

        $this->fixture->harness->ha->pushState(self::LUX_SENSOR, '900');
        $this->fixture->advanceTime(Duration::minutes(4));
        self::assertSame([], $this->recorder->firedStrategies);

        $this->fixture->advanceTime(Duration::minutes(1));

        self::assertSame(['light_level'], $this->recorder->firedStrategies);
    }

    public function testBelowFiresWhenFallingBelowThreshold(): void
    {
        $this->fixture->harness->ha->pushState(self::LUX_SENSOR, '1000');
        $this->attachLightLevel(ThresholdDirection::Below)->arm($this->recorder->recordStrategyFiring(...));

        $this->fixture->harness->ha->pushState(self::LUX_SENSOR, '900');
        $this->fixture->harness->ha->pushState(self::LUX_SENSOR, '700');
        $this->fixture->harness->ha->pushState(self::LUX_SENSOR, '600');

        self::assertSame(['light_level'], $this->recorder->firedStrategies);
    }

    public function testRewatchesWhenThresholdChanged(): void
    {
        $trigger = $this->attachLightLevel(ThresholdDirection::Above);
        $trigger->arm($this->recorder->recordStrategyFiring(...));

        $this->fixture->changeNumber(self::THRESHOLD_KEY, 300.0);
        $this->fixture->harness->ha->pushState(self::LUX_SENSOR, '400');

        self::assertSame(['light_level'], $this->recorder->firedStrategies);
        self::assertSame('sensor.outdoor_lux above 300', $trigger->describe());
    }

    public function testSeedsThresholdWhenHomeAssistantHasNone(): void
    {
        $this->fixture->restoreExposedValue(self::THRESHOLD_KEY, null);

        $this->attachLightLevel(ThresholdDirection::Above);

        self::assertSame(800.0, $this->fixture->harness->entities->requireNumber(self::THRESHOLD_KEY)->getValue());
    }

    public function testUsesRestoredThreshold(): void
    {
        $this->fixture->restoreExposedValue(self::THRESHOLD_KEY, 2000);

        self::assertSame(
            'sensor.outdoor_lux above 2000',
            $this->attachLightLevel(ThresholdDirection::Above)->describe(),
        );
    }

    private function attachLightLevel(ThresholdDirection $direction, ?Duration $holdDuration = null): StrategyTrigger
    {
        return new ThresholdStrategy(
            'light_level',
            new EntityId(self::LUX_SENSOR),
            $direction,
            800.0,
            new ThresholdRange(0.0, 100_000.0, unit: 'lx'),
            holdDuration: $holdDuration,
        )->attach($this->fixture->createTransitionContext('day'));
    }
}
