<?php

declare(strict_types=1);

namespace App\Tests\Shared\HouseMode;

use App\Tests\ServiceContainer;
use PHPUnit\Framework\TestCase;
use Shared\HouseMode\Config\ModeTransitionMapper;
use Shared\HouseMode\TransitionMonitor;
use Shared\HouseMode\WatchedTransition;
use Stewart\Contracts\Exception\CommandException;
use Stewart\Contracts\Exposure\SelectConfig;
use Stewart\Contracts\Time\Duration;

final class WatchedTransitionTest extends TestCase
{
    private const string BED_SENSOR   = 'binary_sensor.bed_occupied';
    private const string ROOM_SENSOR  = 'binary_sensor.room_occupied';
    private const string STRATEGY_KEY = 'activation_strategy';
    private const array STRATEGIES    = [
        ['name' => 'bed_presence', 'type' => 'state', 'entity' => self::BED_SENSOR],
        ['name' => 'room_presence', 'type' => 'state', 'entity' => self::ROOM_SENSOR],
        ['name' => 'late', 'type' => 'fixed_time', 'time' => '23:00'],
    ];
    private const array ALWAYS_ACTIVE = [['name' => 'forced_activation', 'type' => 'fixed_time', 'time' => '13:00']];

    private ModeFixture $fixture;

    private FiringRecorder $recorder;

    private ServiceContainer $container;

    protected function setUp(): void
    {
        $this->fixture   = new ModeFixture('2026-10-14 12:00');
        $this->recorder  = new FiringRecorder();
        $this->container = new ServiceContainer(ModeTransitionMapper::class, TransitionMonitor::class);
        $this->container->replaceSyntheticWithFakes($this->fixture->harness);
        $this->fixture->harness->ha->pushState(self::BED_SENSOR, 'off');
        $this->fixture->harness->ha->pushState(self::ROOM_SENSOR, 'off');
    }

    public function testExposesSelectAndParametersOfAllStrategies(): void
    {
        $this->startWatching();

        $config = $this->fixture->harness->entities->requireSelect(self::STRATEGY_KEY)->getConfig();

        self::assertInstanceOf(SelectConfig::class, $config);
        self::assertSame(['bed_presence', 'room_presence', 'late'], $config->options);
        self::assertSame('Activation strategy', $config->name);
        $this->fixture->harness->entities->requireTime('activation_late_time');
        $this->fixture->harness->entities->requireTime('activation_forced_activation_time');
    }

    public function testArmsOnlyDefaultWhenNothingRestored(): void
    {
        $this->startWatching();

        $this->fixture->harness->ha->pushState(self::ROOM_SENSOR, 'on');
        $this->fixture->harness->ha->pushState(self::BED_SENSOR, 'on');

        self::assertSame(['activation:bed_presence'], $this->recorder->firedStrategies);
        self::assertTrue($this->fixture->harness->entities->requireSelect(self::STRATEGY_KEY)->changes->isEmpty());
    }

    public function testArmsRestoredStrategy(): void
    {
        $this->fixture->restoreExposedValue(self::STRATEGY_KEY, 'room_presence');
        $this->startWatching();

        $this->fixture->harness->ha->pushState(self::BED_SENSOR, 'on');
        $this->fixture->harness->ha->pushState(self::ROOM_SENSOR, 'on');

        self::assertSame(['activation:room_presence'], $this->recorder->firedStrategies);
    }

    public function testReplacesUnknownRestoredStrategyWithDefault(): void
    {
        $this->fixture->restoreExposedValue(self::STRATEGY_KEY, 'removed');
        $this->startWatching();

        $this->fixture->harness->ha->pushState(self::BED_SENSOR, 'on');

        self::assertSame(
            'bed_presence',
            $this->fixture->harness->entities->requireSelect(self::STRATEGY_KEY)->getOption(),
        );
        self::assertSame(['activation:bed_presence'], $this->recorder->firedStrategies);
    }

    public function testSwitchesStrategyOnSelectCommand(): void
    {
        $this->startWatching();

        $this->fixture->selectOption(self::STRATEGY_KEY, 'room_presence');
        $this->fixture->harness->ha->pushState(self::BED_SENSOR, 'on');
        $this->fixture->harness->ha->pushState(self::ROOM_SENSOR, 'on');

        self::assertSame(['activation:room_presence'], $this->recorder->firedStrategies);
    }

    public function testRejectsUnknownSelectCommand(): void
    {
        $this->startWatching();

        $this->expectException(CommandException::class);

        $this->fixture->selectOption(self::STRATEGY_KEY, 'removed');
    }

    public function testFiresAlwaysActiveBesideSelection(): void
    {
        $this->startWatching();

        $this->fixture->harness->ha->pushState(self::BED_SENSOR, 'on');
        $this->fixture->advanceTime(Duration::hours(1));

        self::assertSame(
            ['activation:bed_presence', 'activation:forced_activation'],
            $this->recorder->firedStrategies,
        );
    }

    public function testSkipsFiringOutsideWindow(): void
    {
        $this->startWatching(['from' => '18:00', 'until' => '03:00']);

        $this->fixture->harness->ha->pushState(self::BED_SENSOR, 'on');
        $this->fixture->advanceTime(Duration::hours(6));
        $this->fixture->harness->ha->pushState(self::BED_SENSOR, 'off');
        $this->fixture->harness->ha->pushState(self::BED_SENSOR, 'on');

        self::assertSame(['activation:bed_presence'], $this->recorder->firedStrategies);
    }

    public function testStopsFiringAfterStopWatching(): void
    {
        $this->startWatching()->stopWatching();

        $this->fixture->harness->ha->pushState(self::BED_SENSOR, 'on');
        $this->fixture->advanceTime(Duration::hours(12));

        self::assertSame([], $this->recorder->firedStrategies);
    }

    /** @param array<string, string>|null $window */
    private function startWatching(?array $window = null): WatchedTransition
    {
        $transition = $this->container->getService(ModeTransitionMapper::class)->mapToTransition('activation', [
            'strategies'   => self::STRATEGIES,
            'alwaysActive' => self::ALWAYS_ACTIVE,
            'window'       => $window,
        ]);

        return $this->container->getService(TransitionMonitor::class)->startWatching(
            $transition,
            $this->fixture->createModeContext(),
            $this->recorder->recordTransitionFiring(...),
        );
    }
}
