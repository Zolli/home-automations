<?php

declare(strict_types=1);

namespace App\Tests\Shared\Alert\Rule;

use App\Tests\ServiceContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Alert\Config\InvalidAlertConfig;
use Shared\Alert\Rule\AlertRuleFactory;
use Shared\Alert\Rule\SilenceRule;
use Shared\Alert\Rule\StateMatchRule;
use Shared\Alert\Rule\ThresholdRule;
use Shared\Alert\Rule\UnavailableRule;

final class AlertRuleFactoryTest extends TestCase
{
    private AlertRuleFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ServiceContainer(AlertRuleFactory::class)->getService(AlertRuleFactory::class);
    }

    public function testMapsStateRuleWithDefaults(): void
    {
        $rule = $this->factory->mapToRule(['type' => 'state', 'entities' => ['binary_sensor.kitchen_leak']]);

        self::assertInstanceOf(StateMatchRule::class, $rule);
        self::assertSame('any of binary_sensor.kitchen_leak is on', $rule->describe());
        self::assertSame(0, $rule->getHoldDuration()->toMilliseconds());
    }

    public function testMapsThresholdRulePerDirection(): void
    {
        $above = $this->factory->mapToRule(['type' => 'above', 'entity' => 'sensor.relay_temperature', 'threshold' => 75, 'forSeconds' => 30]);
        $below = $this->factory->mapToRule(['type' => 'below', 'entity' => 'sensor.l1_voltage', 'threshold' => 180.5]);

        self::assertInstanceOf(ThresholdRule::class, $above);
        self::assertSame('sensor.relay_temperature above 75', $above->describe());
        self::assertSame(30_000, $above->getHoldDuration()->toMilliseconds());
        self::assertSame('sensor.l1_voltage below 180.5', $below->describe());
    }

    public function testMapsUnavailableRule(): void
    {
        $unavailable = $this->factory->mapToRule(['type' => 'unavailable', 'entities' => ['binary_sensor.kitchen_leak'], 'forSeconds' => 600]);

        self::assertInstanceOf(UnavailableRule::class, $unavailable);
        self::assertSame(600_000, $unavailable->getHoldDuration()->toMilliseconds());
    }

    public function testMapsMqttTopicSources(): void
    {
        $phaseLoss = $this->factory->mapToRule([
            'type' => 'below',
            'topics' => ['dsmr/reading/phase_voltage_l1', 'dsmr/reading/phase_voltage_l2'],
            'threshold' => 50,
            'unavailableIsFault' => true,
        ]);
        $threshold = $this->factory->mapToRule(['type' => 'above', 'topic' => 'dsmr/reading/phase_voltage_l1', 'threshold' => 253]);

        self::assertSame('dsmr/reading/phase_voltage_l1, dsmr/reading/phase_voltage_l2 below 50 or unavailable', $phaseLoss->describe());
        self::assertSame('dsmr/reading/phase_voltage_l1 above 253', $threshold->describe());
    }

    public function testMapsSilenceRule(): void
    {
        $rule = $this->factory->mapToRule(['type' => 'silent', 'topics' => ['dsmr/reading/phase_voltage_l1'], 'maxSilenceSeconds' => 120]);

        self::assertInstanceOf(SilenceRule::class, $rule);
        self::assertSame('any of dsmr/reading/phase_voltage_l1 silent for over 120 s', $rule->describe());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidRules(): iterable
    {
        yield 'missing type' => [['entities' => ['binary_sensor.leak']]];
        yield 'unknown type' => [['type' => 'between']];
        yield 'missing entities' => [['type' => 'state']];
        yield 'invalid entity id' => [['type' => 'state', 'entities' => ['leak']]];
        yield 'non-numeric threshold' => [['type' => 'above', 'entity' => 'sensor.t', 'threshold' => 'hot']];
        yield 'negative hysteresis' => [['type' => 'above', 'entity' => 'sensor.t', 'threshold' => 1, 'hysteresis' => -1]];
        yield 'threshold missing' => [['type' => 'below', 'entities' => ['sensor.l1_voltage']]];
        yield 'non-boolean unavailable option' => [['type' => 'below', 'entity' => 'sensor.l1_voltage', 'threshold' => 50, 'unavailableIsFault' => 'yes']];
        yield 'wildcard topic' => [['type' => 'below', 'topics' => ['dsmr/reading/#'], 'threshold' => 50]];
        yield 'threshold without entity or topic' => [['type' => 'above', 'threshold' => 1]];
        yield 'empty sources' => [['type' => 'state', 'entities' => [], 'topics' => []]];
        yield 'silence on entities' => [['type' => 'silent', 'entities' => ['sensor.l1_voltage'], 'maxSilenceSeconds' => 60]];
        yield 'zero max silence' => [['type' => 'silent', 'topics' => ['dsmr/reading/phase_voltage_l1'], 'maxSilenceSeconds' => 0]];
        yield 'negative hold' => [['type' => 'state', 'entities' => ['binary_sensor.leak'], 'forSeconds' => -5]];
    }

    /** @param array<string, mixed> $fields */
    #[DataProvider('invalidRules')]
    public function testRejectsInvalidRule(array $fields): void
    {
        $this->expectException(InvalidAlertConfig::class);

        $this->factory->mapToRule($fields);
    }
}
