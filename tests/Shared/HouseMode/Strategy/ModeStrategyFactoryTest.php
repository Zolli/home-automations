<?php

declare(strict_types=1);

namespace App\Tests\Shared\HouseMode\Strategy;

use App\Tests\ServiceContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Config\InvalidTypedOptions;
use Shared\HouseMode\Strategy\Collection\InvalidModeStrategyCollection;
use Shared\HouseMode\Strategy\FixedTimeStrategy;
use Shared\HouseMode\Strategy\ModeStrategyFactory;
use Shared\HouseMode\Strategy\StateStrategy;
use Shared\HouseMode\Strategy\SunEventStrategy;
use Shared\HouseMode\Strategy\ThresholdStrategy;
use Shared\Threshold\ThresholdDirection;
use Stewart\Contracts\Sun\SunEvent;

final class ModeStrategyFactoryTest extends TestCase
{
    private ModeStrategyFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ServiceContainer(ModeStrategyFactory::class)->getService(ModeStrategyFactory::class);
    }

    public function testMapsEveryStrategyType(): void
    {
        $strategies = $this->factory->mapToStrategies([
            ['name' => 'fixed_time', 'type' => 'fixed_time', 'time' => '07:00'],
            ['name' => 'covers_opened', 'type' => 'state', 'entity' => 'binary_sensor.covers_opened'],
            ['name' => 'bright', 'type' => 'above', 'entity' => 'sensor.lux', 'threshold' => 800, 'max' => 100_000],
            ['name' => 'dark', 'type' => 'below', 'entity' => 'sensor.lux', 'threshold' => 50, 'max' => 100_000],
            ['name' => 'sunrise', 'type' => 'sun', 'event' => 'sunrise', 'offsetSeconds' => -600],
        ]);

        $fixedTime = $strategies->find('fixed_time');
        $state     = $strategies->find('covers_opened');
        $bright    = $strategies->find('bright');
        $dark      = $strategies->find('dark');
        $sunrise   = $strategies->find('sunrise');

        self::assertSame(['fixed_time', 'covers_opened', 'bright', 'dark', 'sunrise'], $strategies->listNames());
        self::assertInstanceOf(FixedTimeStrategy::class, $fixedTime);
        self::assertSame('07:00:00', $fixedTime->initialTime->format());
        self::assertInstanceOf(StateStrategy::class, $state);
        self::assertSame('on', $state->state);
        self::assertNull($state->holdDuration);
        self::assertInstanceOf(ThresholdStrategy::class, $bright);
        self::assertSame(ThresholdDirection::Above, $bright->direction);
        self::assertInstanceOf(ThresholdStrategy::class, $dark);
        self::assertSame(ThresholdDirection::Below, $dark->direction);
        self::assertInstanceOf(SunEventStrategy::class, $sunrise);
        self::assertSame(SunEvent::Sunrise, $sunrise->event);
        self::assertTrue($sunrise->offset?->isBeforeEvent());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidStrategies(): iterable
    {
        $lux = ['name' => 'x', 'type' => 'above', 'entity' => 'sensor.lux'];

        yield 'unknown type' => [['name' => 'x', 'type' => 'moon']];
        yield 'missing name' => [['type' => 'fixed_time', 'time' => '07:00']];
        yield 'name unfit for entity key' => [['name' => 'Fixed Time', 'type' => 'fixed_time', 'time' => '07:00']];
        yield 'malformed time' => [['name' => 'x', 'type' => 'fixed_time', 'time' => '7am']];
        yield 'missing entity' => [['name' => 'x', 'type' => 'state']];
        yield 'threshold outside range' => [[...$lux, 'threshold' => 500, 'max' => 100]];
        yield 'max not above min' => [[...$lux, 'threshold' => 10, 'min' => 10, 'max' => 10]];
        yield 'unknown sun event' => [['name' => 'x', 'type' => 'sun', 'event' => 'moonrise']];
    }

    /** @param array<string, mixed> $fields */
    #[DataProvider('invalidStrategies')]
    public function testRejectsInvalidStrategy(array $fields): void
    {
        $this->expectException(InvalidTypedOptions::class);

        $this->factory->mapToStrategies([$fields]);
    }

    public function testRejectsDuplicateStrategyName(): void
    {
        $this->expectException(InvalidModeStrategyCollection::class);

        $this->factory->mapToStrategies([
            ['name' => 'x', 'type' => 'fixed_time', 'time' => '07:00'],
            ['name' => 'x', 'type' => 'sun', 'event' => 'sunrise'],
        ]);
    }
}
