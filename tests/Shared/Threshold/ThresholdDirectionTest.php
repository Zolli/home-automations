<?php

declare(strict_types=1);

namespace App\Tests\Shared\Threshold;

use PHPUnit\Framework\TestCase;
use Shared\Threshold\ThresholdDirection;
use Stewart\Contracts\State\StateChange;
use Stewart\Contracts\Time\Duration;
use Stewart\Testing\App\AppHarness;

final class ThresholdDirectionTest extends TestCase
{
    private const string LUX_SENSOR = 'sensor.outdoor_lux';

    private AppHarness $harness;

    /** @var list<string> */
    private array $crossedValues = [];

    protected function setUp(): void
    {
        $this->harness = new AppHarness();
    }

    public function testAboveFiltersRisingCrossingOnce(): void
    {
        $this->harness->ha->pushState(self::LUX_SENSOR, '50');
        $this->watchCrossings(ThresholdDirection::Above, null);

        $this->harness->ha->pushState(self::LUX_SENSOR, '150');
        $this->harness->ha->pushState(self::LUX_SENSOR, '200');

        self::assertSame(['150'], $this->crossedValues);
    }

    public function testBelowFiltersFallingCrossingAfterHold(): void
    {
        $this->harness->ha->pushState(self::LUX_SENSOR, '500');
        $this->watchCrossings(ThresholdDirection::Below, Duration::minutes(5));

        $this->harness->ha->pushState(self::LUX_SENSOR, '50');
        $this->harness->advanceTime(Duration::minutes(4));
        self::assertSame([], $this->crossedValues);

        $this->harness->advanceTime(Duration::minutes(1));

        self::assertSame(['50'], $this->crossedValues);
    }

    private function watchCrossings(ThresholdDirection $direction, ?Duration $holdDuration): void
    {
        $direction
            ->filterCrossings($this->harness->ha->watchStateChanges(self::LUX_SENSOR), 100.0, $holdDuration)
            ->subscribe(function (StateChange $change): void {
                $this->crossedValues[] = (string) $change->to?->state;
            });
    }
}
