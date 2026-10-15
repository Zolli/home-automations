<?php

declare(strict_types=1);

namespace App\Tests\HouseMode;

use App\HouseMode\DayNightModeApp;
use App\Tests\Shared\HouseMode\ModeFixture;
use PHPUnit\Framework\TestCase;
use Shared\HouseMode\InvalidModeConfig;
use Shared\HouseMode\ModeTransitionMapper;
use Shared\HouseMode\Payload\ModeEventPayloadMapper;
use Stewart\Contracts\Time\Duration;

final class DayNightModeAppTest extends TestCase
{
    private const string MODE_SELECT  = 'input_select.house_day_night_mode';
    private const string LUX_SENSOR   = 'sensor.outdoor_illuminance_lux_average';
    private const string COVERS       = 'binary_sensor.covers_closed_all';
    private const string TOPIC        = 'home/event/day-night-mode';
    private const string DAY_STRATEGY = 'day_strategy';
    private const array DAY           = [
        'defaultStrategy' => 'sun_above_horizon',
        'strategies'      => [
            ['name' => 'sun_above_horizon', 'type' => 'sun', 'event' => 'sunrise'],
            ['name' => 'fixed_time', 'type' => 'fixed_time', 'time' => '07:30'],
            [
                'name'       => 'light_level',
                'type'       => 'above',
                'entity'     => self::LUX_SENSOR,
                'threshold'  => 30,
                'min'        => 10,
                'max'        => 200,
                'step'       => 10,
                'forSeconds' => 300,
            ],
        ],
    ];
    private const array NIGHT = [
        'strategies' => [
            ['name' => 'covers_closed', 'type' => 'state', 'entity' => self::COVERS],
            ['name' => 'sun_below_horizon', 'type' => 'sun', 'event' => 'sunset'],
        ],
    ];

    private ModeFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new ModeFixture('2026-10-14 05:00');
        $harness       = $this->fixture->harness;
        $harness->provideService(ModeTransitionMapper::class, $this->fixture->createTransitionMapper());
        $harness->provideService(ModeEventPayloadMapper::class, new ModeEventPayloadMapper());
        $harness->ha->pushState(self::MODE_SELECT, 'night');
        $harness->ha->pushState(self::COVERS, 'off');
    }

    public function testSelectsDayAndPublishesAtSunrise(): void
    {
        $this->startApp();

        $this->fixture->advanceTime(Duration::hours(1));

        self::assertSame(['input_select.select_option'], $this->fixture->listCalledServices());
        self::assertSame(['option' => 'day'], $this->fixture->harness->ha->getLastCall()->data);
        self::assertSame(
            [[self::TOPIC, [
                'event' => 'day',
                'data'  => ['cause' => 'sun_above_horizon'],
                'meta'  => ['producer' => 'stewart/day-night-mode'],
            ]]],
            $this->fixture->listPublishedMessages(),
        );
    }

    public function testSelectsNightWhenCoversClose(): void
    {
        $this->startApp();

        $this->fixture->harness->ha->pushState(self::COVERS, 'on');

        self::assertSame(['option' => 'night'], $this->fixture->harness->ha->getLastCall()->data);
        self::assertSame('night', $this->readPublishedEvent());
    }

    public function testEmitsDayEvenWhenAlreadyDay(): void
    {
        $this->fixture->harness->ha->pushState(self::MODE_SELECT, 'day');
        $this->startApp();

        $this->fixture->advanceTime(Duration::hours(1));

        self::assertSame(['input_select.select_option'], $this->fixture->listCalledServices());
        self::assertSame('day', $this->readPublishedEvent());
    }

    public function testSwitchesToFixedTimeOnSelectCommand(): void
    {
        $this->startApp();

        $this->fixture->selectOption(self::DAY_STRATEGY, 'fixed_time');
        $this->fixture->advanceTime(Duration::hours(2));
        self::assertSame([], $this->fixture->listCalledServices());

        $this->fixture->advanceTime(Duration::minutes(30));

        self::assertSame(['input_select.select_option'], $this->fixture->listCalledServices());
    }

    public function testLightLevelNeedsFiveMinutesAbove(): void
    {
        $this->fixture->harness->ha->pushState(self::LUX_SENSOR, '10');
        $this->startApp();
        $this->fixture->selectOption(self::DAY_STRATEGY, 'light_level');

        $this->fixture->harness->ha->pushState(self::LUX_SENSOR, '40');
        $this->fixture->advanceTime(Duration::minutes(4));
        self::assertSame([], $this->fixture->listCalledServices());

        $this->fixture->advanceTime(Duration::minutes(1));

        self::assertSame(['option' => 'day'], $this->fixture->harness->ha->getLastCall()->data);
    }

    public function testStopsSwitchingAfterStop(): void
    {
        $this->startApp();

        $this->fixture->harness->stopApp();
        $this->fixture->advanceTime(Duration::hours(1));

        self::assertSame([], $this->fixture->listCalledServices());
    }

    public function testRejectsInvalidConfigAtConstruction(): void
    {
        $this->expectException(InvalidModeConfig::class);

        $this->fixture->harness->startApp(DayNightModeApp::class, ['day' => [], 'night' => self::NIGHT]);
    }

    private function startApp(): void
    {
        $this->fixture->harness->startApp(DayNightModeApp::class, ['day' => self::DAY, 'night' => self::NIGHT]);
    }

    private function readPublishedEvent(): mixed
    {
        $payload = $this->fixture->harness->mqtt->published->getLast()?->decodeJsonPayload();

        return \is_array($payload) ? $payload['event'] ?? null : null;
    }
}
