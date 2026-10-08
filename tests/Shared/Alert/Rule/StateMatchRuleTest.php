<?php

declare(strict_types=1);

namespace App\Tests\Shared\Alert\Rule;

use PHPUnit\Framework\TestCase;
use Shared\Alert\Reading\ReadingSnapshot;
use Shared\Alert\Rule\StateMatchRule;
use Stewart\Contracts\Time\Duration;

final class StateMatchRuleTest extends TestCase
{
    public function testFindsFirstEntityInFaultState(): void
    {
        $fault = $this->createSmokeRule()->findFault($this->createReadings(kitchen: 'off', hall: 'on'));

        self::assertSame('binary_sensor.hall_smoke', $fault?->source->key);
        self::assertSame('Hall smoke is on', $fault->reason);
    }

    public function testRecoversWhenNoEntityMatches(): void
    {
        $rule = $this->createSmokeRule();

        self::assertTrue($rule->isRecovered($this->createReadings(kitchen: 'off', hall: 'off')));
        self::assertFalse($rule->isRecovered($this->createReadings(kitchen: 'on', hall: 'off')));
    }

    public function testIgnoresMissingEntities(): void
    {
        self::assertNull($this->createSmokeRule()->findFault(ReadingFixture::collectReadings()));
    }

    private function createSmokeRule(): StateMatchRule
    {
        return new StateMatchRule(
            ReadingFixture::createEntitySources('binary_sensor.kitchen_smoke', 'binary_sensor.hall_smoke'),
            'on',
            Duration::zero(),
        );
    }

    private function createReadings(string $kitchen, string $hall): ReadingSnapshot
    {
        return ReadingFixture::collectReadings(
            ReadingFixture::createEntityReading('binary_sensor.kitchen_smoke', $kitchen),
            ReadingFixture::createEntityReading('binary_sensor.hall_smoke', $hall, ['friendly_name' => 'Hall smoke']),
        );
    }
}
