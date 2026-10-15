<?php

declare(strict_types=1);

namespace App\Tests\Shared\HouseMode\Trigger;

use App\Tests\ServiceContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Config\InvalidTypedOptions;
use Shared\HouseMode\Trigger\FixedTimeTrigger;
use Shared\HouseMode\Trigger\ModeTrigger;
use Shared\HouseMode\Trigger\ModeTriggerFactory;
use Shared\HouseMode\Trigger\StateTrigger;
use Shared\HouseMode\Trigger\SunEventTrigger;
use Shared\HouseMode\Trigger\ThresholdTrigger;

final class ModeTriggerFactoryTest extends TestCase
{
    private const array LUX = ['name' => 'lux', 'type' => 'above', 'entity' => 'sensor.lux'];

    private ModeTriggerFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ServiceContainer(ModeTriggerFactory::class)->getService(ModeTriggerFactory::class);
    }

    /** @return iterable<string, array{array<string, mixed>, class-string<ModeTrigger>, string}> */
    public static function triggers(): iterable
    {
        yield 'fixed time' => [
            ['name' => 'fixed_time', 'type' => 'fixed_time', 'time' => '07:00'],
            FixedTimeTrigger::class,
            'daily at 07:00:00',
        ];
        yield 'state' => [
            ['name' => 'covers_opened', 'type' => 'state', 'entity' => 'binary_sensor.covers_opened'],
            StateTrigger::class,
            'binary_sensor.covers_opened turns on',
        ];
        yield 'above' => [
            ['name' => 'bright', 'type' => 'above', 'entity' => 'sensor.lux', 'threshold' => 800, 'max' => 1_000],
            ThresholdTrigger::class,
            'sensor.lux above 800',
        ];
        yield 'below' => [
            ['name' => 'dark', 'type' => 'below', 'entity' => 'sensor.lux', 'threshold' => 50, 'max' => 1_000],
            ThresholdTrigger::class,
            'sensor.lux below 50',
        ];
        yield 'sun' => [
            ['name' => 'sunrise', 'type' => 'sun', 'event' => 'sunrise', 'offsetSeconds' => -600],
            SunEventTrigger::class,
            'at sunrise -00:10:00',
        ];
    }

    /**
     * @param array<string, mixed> $fields
     * @param class-string<ModeTrigger> $triggerClass
     */
    #[DataProvider('triggers')]
    public function testMapsTriggerPerType(array $fields, string $triggerClass, string $description): void
    {
        $trigger = $this->factory->mapToTrigger($fields);

        self::assertInstanceOf($triggerClass, $trigger);
        self::assertSame($fields['name'], $trigger->getName());
        self::assertSame($description, $trigger->describe());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidTriggers(): iterable
    {
        yield 'missing type' => [['name' => 'x', 'time' => '07:00']];
        yield 'unknown type' => [['name' => 'x', 'type' => 'moon']];
        yield 'missing name' => [['type' => 'fixed_time', 'time' => '07:00']];
        yield 'name unfit for key' => [['name' => 'Bed', 'type' => 'state', 'entity' => 'sun.sun']];
        yield 'malformed time' => [['name' => 'x', 'type' => 'fixed_time', 'time' => '7am']];
        yield 'missing entity' => [['name' => 'x', 'type' => 'state']];
        yield 'threshold outside range' => [[...self::LUX, 'threshold' => 500, 'max' => 100]];
        yield 'max not above min' => [[...self::LUX, 'threshold' => 10, 'min' => 10, 'max' => 10]];
        yield 'zero step' => [[...self::LUX, 'threshold' => 10, 'max' => 100, 'step' => 0]];
        yield 'unknown sun event' => [['name' => 'x', 'type' => 'sun', 'event' => 'moonrise']];
    }

    /** @param array<string, mixed> $fields */
    #[DataProvider('invalidTriggers')]
    public function testRejectsInvalidTrigger(array $fields): void
    {
        $this->expectException(InvalidTypedOptions::class);

        $this->factory->mapToTrigger($fields);
    }
}
