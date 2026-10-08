<?php

declare(strict_types=1);

namespace App\Tests\Shared\Alert\Rule;

use PHPUnit\Framework\TestCase;
use Shared\Alert\Reading\ReadingSnapshot;
use Shared\Alert\Rule\ThresholdDirection;
use Shared\Alert\Rule\ThresholdRule;
use Stewart\Contracts\Time\Duration;

final class ThresholdRuleTest extends TestCase
{
    public function testFindsFaultBeyondThreshold(): void
    {
        $rule = $this->createVoltageRule(ThresholdDirection::Above, 250.0);

        self::assertSame('Main voltage is 253.5, above 250', $rule->findFault($this->createReadings('253.5'))?->reason);
        self::assertNull($rule->findFault($this->createReadings('250')));
    }

    public function testFindsFaultBelowThreshold(): void
    {
        $rule = $this->createVoltageRule(ThresholdDirection::Below, 200.0);

        self::assertNotNull($rule->findFault($this->createReadings('190')));
        self::assertNull($rule->findFault($this->createReadings('230')));
    }

    public function testIgnoresNonNumericState(): void
    {
        $rule = $this->createVoltageRule(ThresholdDirection::Above, 250.0);

        self::assertNull($rule->findFault($this->createReadings('unavailable')));
        self::assertFalse($rule->isRecovered($this->createReadings('unavailable')));
    }

    public function testRecoversOnlyPastHysteresis(): void
    {
        $rule = $this->createVoltageRule(ThresholdDirection::Below, 200.0, hysteresis: 10.0);

        self::assertFalse($rule->isRecovered($this->createReadings('205')));
        self::assertTrue($rule->isRecovered($this->createReadings('210')));
    }

    public function testReadsAttributeWhenConfigured(): void
    {
        $rule = new ThresholdRule(ReadingFixture::createEntitySources('sensor.main_voltage'), ThresholdDirection::Above, 30.0, 0.0, Duration::zero(), 'temperature');
        $readings = ReadingFixture::collectReadings(ReadingFixture::createEntityReading('sensor.main_voltage', '230', ['temperature' => 31]));

        self::assertNotNull($rule->findFault($readings));
    }

    public function testFindsFirstPhaseBelowThreshold(): void
    {
        $fault = $this->createPhaseRule()->findFault($this->createPhaseReadings('231', '12.5', '229'));

        self::assertSame('sensor.l2_voltage', $fault?->source->key);
        self::assertSame('sensor.l2_voltage is 12.5, below 50', $fault->reason);
    }

    public function testTreatsUnavailableAsFaultWhenEnabled(): void
    {
        $readings = $this->createPhaseReadings('231', '230', 'unavailable');

        self::assertSame('sensor.l3_voltage is unavailable', $this->createPhaseRule()->findFault($readings)?->reason);
        self::assertNull($this->createPhaseRule(unavailableIsFault: false)->findFault($readings));
    }

    public function testRecoversWhenAllSourcesPastHysteresis(): void
    {
        $rule = $this->createPhaseRule();

        self::assertFalse($rule->isRecovered($this->createPhaseReadings('231', '55', '229')));
        self::assertFalse($rule->isRecovered($this->createPhaseReadings('231', 'unknown', '229')));
        self::assertTrue($rule->isRecovered($this->createPhaseReadings('231', '60', '229')));
    }

    public function testRecoversIgnoringUnavailableWhenNotFault(): void
    {
        $rule = $this->createPhaseRule(unavailableIsFault: false);

        self::assertTrue($rule->isRecovered($this->createPhaseReadings('231', 'unavailable', '229')));
        self::assertFalse($rule->isRecovered($this->createPhaseReadings('231', 'unavailable', '55')));
    }

    public function testRecoversIgnoringSourcesWithoutReading(): void
    {
        $readings = ReadingFixture::collectReadings(ReadingFixture::createEntityReading('sensor.l1_voltage', '231'));

        self::assertTrue($this->createPhaseRule()->isRecovered($readings));
    }

    public function testStaysRaisedWithoutAnyNumericReading(): void
    {
        $rule = $this->createPhaseRule(unavailableIsFault: false);

        self::assertFalse($rule->isRecovered($this->createPhaseReadings('unavailable', 'unknown', 'unavailable')));
        self::assertFalse($rule->isRecovered(ReadingFixture::collectReadings()));
    }

    private function createPhaseRule(bool $unavailableIsFault = true): ThresholdRule
    {
        return new ThresholdRule(
            ReadingFixture::createEntitySources('sensor.l1_voltage', 'sensor.l2_voltage', 'sensor.l3_voltage'),
            ThresholdDirection::Below,
            50.0,
            10.0,
            Duration::zero(),
            unavailableIsFault: $unavailableIsFault,
        );
    }

    private function createPhaseReadings(string $l1, string $l2, string $l3): ReadingSnapshot
    {
        return ReadingFixture::collectReadings(
            ReadingFixture::createEntityReading('sensor.l1_voltage', $l1),
            ReadingFixture::createEntityReading('sensor.l2_voltage', $l2),
            ReadingFixture::createEntityReading('sensor.l3_voltage', $l3),
        );
    }

    private function createVoltageRule(ThresholdDirection $direction, float $threshold, float $hysteresis = 0.0): ThresholdRule
    {
        return new ThresholdRule(ReadingFixture::createEntitySources('sensor.main_voltage'), $direction, $threshold, $hysteresis, Duration::zero());
    }

    private function createReadings(string $voltage): ReadingSnapshot
    {
        return ReadingFixture::collectReadings(
            ReadingFixture::createEntityReading('sensor.main_voltage', $voltage, ['friendly_name' => 'Main voltage']),
        );
    }
}
