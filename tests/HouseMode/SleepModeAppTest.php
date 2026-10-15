<?php

declare(strict_types=1);

namespace App\Tests\HouseMode;

use App\HouseMode\SleepModeApp;
use App\Tests\Shared\HouseMode\ModeFixture;
use PHPUnit\Framework\TestCase;
use Shared\HouseMode\ModeTransitionMapper;
use Shared\HouseMode\Payload\ModeEventPayloadMapper;
use Stewart\Contracts\Time\Duration;

final class SleepModeAppTest extends TestCase
{
    private const string SLEEP_SWITCH     = 'input_boolean.sleep_mode';
    private const string BED_FULL         = 'binary_sensor.master_bed_fully_occupied';
    private const string BED_PARTIAL      = 'binary_sensor.master_bed_partially_occupied';
    private const string BEDROOM_COVERS   = 'cover.cover_group_master_bedroom';
    private const string TOPIC            = 'home/event/sleep-mode';
    private const string DEACTIVATION_KEY = 'deactivation_strategy';
    private const array ACTIVATION        = [
        'window'          => ['from' => '18:00', 'until' => '03:00'],
        'defaultStrategy' => 'bed_presence_double',
        'strategies'      => [
            ['name' => 'bed_presence_single', 'type' => 'state', 'entity' => self::BED_PARTIAL, 'forSeconds' => 1_800],
            ['name' => 'bed_presence_double', 'type' => 'state', 'entity' => self::BED_FULL, 'forSeconds' => 1_800],
        ],
        'alwaysActive'    => [['name' => 'forced_activation', 'type' => 'fixed_time', 'time' => '02:00']],
    ];
    private const array DEACTIVATION = [
        'window'     => ['from' => '04:00', 'until' => '12:00'],
        'strategies' => [
            [
                'name'       => 'bed_presence',
                'type'       => 'state',
                'entity'     => self::BED_PARTIAL,
                'state'      => 'off',
                'forSeconds' => 600,
            ],
            [
                'name'       => 'covers_opened',
                'type'       => 'state',
                'entity'     => self::BEDROOM_COVERS,
                'state'      => 'open',
                'forSeconds' => 120,
            ],
        ],
    ];

    public function testSleepsAfterBedOccupiedHalfHour(): void
    {
        $fixture = $this->createFixture('2026-10-14 22:00', 'off');

        $fixture->harness->ha->pushState(self::BED_FULL, 'on');
        $fixture->advanceTime(Duration::minutes(29));
        self::assertSame([], $fixture->listCalledServices());

        $fixture->advanceTime(Duration::minutes(1));

        self::assertSame(['input_boolean.turn_on'], $fixture->listCalledServices());
        self::assertSame(
            [[self::TOPIC, [
                'event' => 'sleep',
                'data'  => ['cause' => 'bed_presence_double'],
                'meta'  => ['producer' => 'stewart/sleep-mode'],
            ]]],
            $fixture->listPublishedMessages(),
        );
    }

    public function testIgnoresBedOccupancyOutsideWindow(): void
    {
        $fixture = $this->createFixture('2026-10-14 15:00', 'off');

        $fixture->harness->ha->pushState(self::BED_FULL, 'on');
        $fixture->advanceTime(Duration::minutes(30));

        self::assertSame([], $fixture->listCalledServices());
        self::assertSame([], $fixture->listPublishedMessages());
    }

    public function testSkipsSleepWhenAlreadyAsleep(): void
    {
        $fixture = $this->createFixture('2026-10-14 22:00', 'on');

        $fixture->harness->ha->pushState(self::BED_FULL, 'on');
        $fixture->advanceTime(Duration::minutes(30));

        self::assertSame([], $fixture->listCalledServices());
    }

    public function testForcedActivationFiresBesideSelection(): void
    {
        $fixture = $this->createFixture('2026-10-15 01:00', 'off');

        $fixture->advanceTime(Duration::hours(1));

        self::assertSame(['input_boolean.turn_on'], $fixture->listCalledServices());
        self::assertSame('forced_activation', $this->readPublishedCause($fixture));
    }

    public function testWakesWhenCoversOpenTwoMinutes(): void
    {
        $fixture = $this->createFixture('2026-10-15 07:00', 'on');
        $fixture->selectOption(self::DEACTIVATION_KEY, 'covers_opened');

        $fixture->harness->ha->pushState(self::BEDROOM_COVERS, 'open');
        $fixture->advanceTime(Duration::minutes(2));

        self::assertSame(['input_boolean.turn_off'], $fixture->listCalledServices());
        self::assertSame('covers_opened', $this->readPublishedCause($fixture));
    }

    public function testSkipsWakeWhenAlreadyAwake(): void
    {
        $fixture = $this->createFixture('2026-10-15 07:00', 'off');

        $fixture->harness->ha->pushState(self::BED_PARTIAL, 'off');
        $fixture->advanceTime(Duration::minutes(10));

        self::assertSame([], $fixture->listCalledServices());
    }

    private function createFixture(string $startTime, string $sleepMode): ModeFixture
    {
        $fixture = new ModeFixture($startTime);
        $harness = $fixture->harness;
        $harness->provideService(ModeTransitionMapper::class, $fixture->createTransitionMapper());
        $harness->provideService(ModeEventPayloadMapper::class, new ModeEventPayloadMapper());

        $harness->ha->pushState(self::SLEEP_SWITCH, $sleepMode);
        $harness->ha->pushState(self::BED_FULL, 'off');
        $harness->ha->pushState(self::BED_PARTIAL, 'on');
        $harness->ha->pushState(self::BEDROOM_COVERS, 'closed');
        $harness->startApp(SleepModeApp::class, [
            'activation'   => self::ACTIVATION,
            'deactivation' => self::DEACTIVATION,
        ]);

        return $fixture;
    }

    private function readPublishedCause(ModeFixture $fixture): mixed
    {
        $payload = $fixture->harness->mqtt->published->getLast()?->decodeJsonPayload();

        return \is_array($payload) && \is_array($payload['data'] ?? null) ? $payload['data']['cause'] ?? null : null;
    }
}
