<?php

declare(strict_types=1);

namespace App\Tests\Shared\HouseMode;

use App\Tests\ServiceContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\HouseMode\InvalidModeConfig;
use Shared\HouseMode\ModeTransitionMapper;

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
        $this->mapper = new ServiceContainer(ModeTransitionMapper::class)->getService(ModeTransitionMapper::class);
    }

    public function testMapsStrategiesWindowAndDefault(): void
    {
        $transition = $this->mapper->mapToTransition('day', [
            'window'          => ['from' => '05:00', 'until' => '12:00'],
            'defaultStrategy' => 'sunrise',
            'strategies'      => [
                ['name' => 'fixed_time', 'type' => 'fixed_time', 'time' => '07:00'],
                ['name' => 'sunrise', 'type' => 'sun', 'event' => 'sunrise'],
            ],
            'alwaysActive'    => [self::FORCED],
        ]);

        self::assertSame(['fixed_time', 'sunrise'], $transition->strategies->listNames());
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
        yield 'no strategies' => [['alwaysActive' => [self::FORCED]]];
        yield 'unknown default' => [[...self::PRESENCE, 'defaultStrategy' => 'removed']];
        yield 'default not a name' => [[...self::PRESENCE, 'defaultStrategy' => 5]];
        yield 'duplicate name' => [['strategies' => [self::BED_PRESENCE, self::BED_PRESENCE]]];
        yield 'name in both lists' => [['strategies' => [self::FORCED], 'alwaysActive' => [self::FORCED]]];
        yield 'invalid strategy' => [['strategies' => [['name' => 'x', 'type' => 'moon']]]];
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
