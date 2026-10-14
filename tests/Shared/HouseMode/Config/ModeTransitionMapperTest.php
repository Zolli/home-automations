<?php

declare(strict_types=1);

namespace App\Tests\Shared\HouseMode\Config;

use App\Tests\ServiceContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\HouseMode\Config\InvalidModeConfig;
use Shared\HouseMode\Config\ModeTransitionMapper;

final class ModeTransitionMapperTest extends TestCase
{
    private const array BED_PRESENCE = [
        'name'       => 'bed_presence',
        'type'       => 'state',
        'entity'     => 'binary_sensor.bed_occupied',
        'forSeconds' => 1_800,
    ];
    private const array ROOM_PRESENCE = [
        'name'   => 'room_presence',
        'type'   => 'state',
        'entity' => 'binary_sensor.room',
    ];
    private const array PRESENCE = ['strategies' => [self::BED_PRESENCE, self::ROOM_PRESENCE]];
    private const array FORCED   = ['name' => 'forced_activation', 'type' => 'fixed_time', 'time' => '01:00'];

    private ModeTransitionMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ServiceContainer(ModeTransitionMapper::class)->getService(ModeTransitionMapper::class);
    }

    public function testMapsStrategiesWindowAndDefault(): void
    {
        $transition = $this->mapper->mapToTransition('activation', [
            'window'          => ['from' => '18:00', 'until' => '03:00'],
            'defaultStrategy' => 'room_presence',
            'strategies'      => [self::BED_PRESENCE, self::ROOM_PRESENCE],
            'alwaysActive'    => [self::FORCED],
        ]);

        self::assertSame('activation', $transition->name);
        self::assertSame(['bed_presence', 'room_presence'], $transition->strategies->listNames());
        self::assertSame(['forced_activation'], $transition->alwaysActive->listNames());
        self::assertSame('room_presence', $transition->defaultStrategyName);
        self::assertSame('18:00:00-03:00:00', $transition->window?->describe());
    }

    public function testDefaultsToFirstStrategyWithoutWindow(): void
    {
        $transition = $this->mapper->mapToTransition('day', self::PRESENCE);

        self::assertSame('bed_presence', $transition->defaultStrategyName);
        self::assertTrue($transition->alwaysActive->isEmpty());
        self::assertNull($transition->window);
    }

    public function testResolvesUnknownSelectionToDefault(): void
    {
        $transition = $this->mapper->mapToTransition('day', self::PRESENCE);

        self::assertSame('room_presence', $transition->resolveStrategyName('room_presence'));
        self::assertSame('bed_presence', $transition->resolveStrategyName('removed'));
        self::assertSame('bed_presence', $transition->resolveStrategyName(null));
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidTransitions(): iterable
    {
        yield 'no strategies' => [['alwaysActive' => [self::FORCED]]];
        yield 'unknown default' => [['strategies' => [self::BED_PRESENCE], 'defaultStrategy' => 'removed']];
        yield 'default not a name' => [['strategies' => [self::BED_PRESENCE], 'defaultStrategy' => 5]];
        yield 'name in both lists' => [['strategies' => [self::FORCED], 'alwaysActive' => [self::FORCED]]];
        yield 'invalid strategy' => [['strategies' => [['name' => 'x', 'type' => 'moon']]]];
        yield 'window missing until' => [['strategies' => [self::BED_PRESENCE], 'window' => ['from' => '18:00']]];
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
