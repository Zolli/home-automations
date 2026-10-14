<?php

declare(strict_types=1);

namespace App\Tests\Shared\HouseMode;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\HouseMode\InvalidModeConfig;
use Shared\HouseMode\ModeTransitionMapper;
use Shared\HouseMode\Trigger\ModeTrigger;

final class ModeTransitionMapperTest extends TestCase
{
    private const array BED_PRESENCE = [
        'name'       => 'bed_presence',
        'type'       => 'state',
        'entity'     => 'binary_sensor.bed_occupied',
        'forSeconds' => 1_800,
    ];
    private const array FORCED   = ['name' => 'forced_activation', 'type' => 'fixed_time', 'time' => '01:00'];
    private const array PRESENCE = ['strategies' => [self::BED_PRESENCE]];

    private ModeTransitionMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ModeTransitionMapper();
    }

    public function testMapsEveryStrategyTypeWindowAndDefault(): void
    {
        $transition = $this->mapper->mapToTransition('day', [
            'window'          => ['from' => '05:00', 'until' => '12:00'],
            'defaultStrategy' => 'sunrise',
            'strategies'      => [
                ['name' => 'fixed_time', 'type' => 'fixed_time', 'time' => '07:00'],
                ['name' => 'covers_opened', 'type' => 'state', 'entity' => 'binary_sensor.covers_opened'],
                ['name' => 'bright', 'type' => 'above', 'entity' => 'sensor.lux', 'threshold' => 800, 'max' => 1_000],
                ['name' => 'dark', 'type' => 'below', 'entity' => 'sensor.lux', 'threshold' => 50, 'max' => 1_000],
                ['name' => 'sunrise', 'type' => 'sun', 'event' => 'sunrise', 'offsetSeconds' => -600],
            ],
            'alwaysActive'    => [self::FORCED],
        ]);

        self::assertSame(
            ['fixed_time', 'covers_opened', 'bright', 'dark', 'sunrise'],
            $transition->strategies->listNames(),
        );
        self::assertSame(
            [
                'daily at 07:00:00',
                'binary_sensor.covers_opened turns on',
                'sensor.lux above 800',
                'sensor.lux below 50',
                'at sunrise -00:10:00',
            ],
            $transition->strategies->mapToList(static fn(ModeTrigger $trigger): string => $trigger->describe()),
        );
        self::assertSame(['forced_activation'], $transition->alwaysActive->listNames());
        self::assertSame('sunrise', $transition->defaultStrategyName);
        self::assertSame('05:00:00-12:00:00', $transition->window?->describe());
    }

    public function testDefaultsToFirstStrategyWithoutWindow(): void
    {
        $transition = $this->mapper->mapToTransition('day', self::PRESENCE);

        self::assertSame('bed_presence', $transition->defaultStrategyName);
        self::assertTrue($transition->alwaysActive->isEmpty());
        self::assertNull($transition->window);
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidTransitions(): iterable
    {
        $lux = ['name' => 'x', 'type' => 'above', 'entity' => 'sensor.lux'];

        yield 'no strategies' => [['alwaysActive' => [self::FORCED]]];
        yield 'unknown default' => [[...self::PRESENCE, 'defaultStrategy' => 'removed']];
        yield 'default not a name' => [[...self::PRESENCE, 'defaultStrategy' => 5]];
        yield 'duplicate name' => [['strategies' => [self::BED_PRESENCE, self::BED_PRESENCE]]];
        yield 'name in both lists' => [['strategies' => [self::FORCED], 'alwaysActive' => [self::FORCED]]];
        yield 'unknown type' => [['strategies' => [['name' => 'x', 'type' => 'moon']]]];
        yield 'missing name' => [['strategies' => [['type' => 'fixed_time', 'time' => '07:00']]]];
        yield 'name unfit for key' => [['strategies' => [['name' => 'Bed', 'type' => 'state', 'entity' => 'sun.sun']]]];
        yield 'malformed time' => [['strategies' => [['name' => 'x', 'type' => 'fixed_time', 'time' => '7am']]]];
        yield 'missing entity' => [['strategies' => [['name' => 'x', 'type' => 'state']]]];
        yield 'threshold outside range' => [['strategies' => [[...$lux, 'threshold' => 500, 'max' => 100]]]];
        yield 'max not above min' => [['strategies' => [[...$lux, 'threshold' => 10, 'min' => 10, 'max' => 10]]]];
        yield 'unknown sun event' => [['strategies' => [['name' => 'x', 'type' => 'sun', 'event' => 'moonrise']]]];
        yield 'window missing until' => [[...self::PRESENCE, 'window' => ['from' => '18:00']]];
        yield 'window malformed' => [[...self::PRESENCE, 'window' => ['from' => '6pm', 'until' => '03:00']]];
        yield 'window empty' => [[...self::PRESENCE, 'window' => ['from' => '18:00', 'until' => '18:00']]];
    }

    /** @param array<string, mixed> $fields */
    #[DataProvider('invalidTransitions')]
    public function testRejectsInvalidTransition(array $fields): void
    {
        $this->expectException(InvalidModeConfig::class);

        $this->mapper->mapToTransition('activation', $fields);
    }
}
