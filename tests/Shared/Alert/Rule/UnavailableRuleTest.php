<?php

declare(strict_types=1);

namespace App\Tests\Shared\Alert\Rule;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Alert\Reading\ReadingSnapshot;
use Shared\Alert\Rule\UnavailableRule;
use Stewart\Contracts\Time\Duration;

final class UnavailableRuleTest extends TestCase
{
    /** @return iterable<string, array{string}> */
    public static function offlineStates(): iterable
    {
        yield 'unavailable' => ['unavailable'];
        yield 'unknown' => ['unknown'];
    }

    #[DataProvider('offlineStates')]
    public function testFindsOfflineSensor(string $state): void
    {
        $fault = $this->createRule()->findFault($this->createReadings($state));

        self::assertSame("Leak sensor is {$state}", $fault?->reason);
    }

    public function testRecoversWhenSensorReportsAgain(): void
    {
        $rule = $this->createRule();

        self::assertTrue($rule->isRecovered($this->createReadings('off')));
        self::assertFalse($rule->isRecovered($this->createReadings('unavailable')));
    }

    private function createRule(): UnavailableRule
    {
        return new UnavailableRule(ReadingFixture::createEntitySources('binary_sensor.kitchen_leak'), Duration::minutes(10));
    }

    private function createReadings(string $state): ReadingSnapshot
    {
        return ReadingFixture::collectReadings(
            ReadingFixture::createEntityReading('binary_sensor.kitchen_leak', $state, ['friendly_name' => 'Leak sensor']),
        );
    }
}
