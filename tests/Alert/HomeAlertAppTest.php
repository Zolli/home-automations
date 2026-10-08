<?php

declare(strict_types=1);

namespace App\Tests\Alert;

use App\Alert\HomeAlertApp;
use App\Tests\ServiceContainer;
use App\Tests\Shared\Alert\HaFake;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shared\Alert\AlertMonitor;
use Shared\Alert\Config\AlertConfigMapper;
use Shared\Alert\Config\InvalidAlertConfig;
use Stewart\Contracts\HaContext;
use Stewart\Contracts\Schedule\Scheduler;
use Stewart\Contracts\Time\Duration;

final class HomeAlertAppTest extends TestCase
{
    private const array POLICIES = [
        'critical' => [
            'steps' => [
                ['destinations' => [['type' => 'notify_service', 'target' => 'notify.mobile_app_phone']]],
                ['afterSeconds' => 120, 'destinations' => [['type' => 'tts', 'target' => 'media_player.home']]],
            ],
        ],
    ];

    private const array TEST_MODE_OFF = [['type' => 'eq', 'field' => 'input_boolean.alarm_test_mode', 'value' => 'off']];

    private const array SMOKE_LEAK_POLICY = [
        'steps' => [
            ['destinations' => [['type' => 'notify_service', 'target' => 'notify.mobile_app_phone']]],
            [
                'destinations' => [[
                    'type' => 'phone_tts',
                    'target' => 'notify.mobile_app_phone',
                    'conditions' => [['type' => 'eq', 'field' => 'input_boolean.alarm_theater_mode', 'value' => 'off']],
                ]],
                'repeat' => ['everySeconds' => 30, 'times' => 5, 'while' => self::TEST_MODE_OFF],
            ],
            [
                'afterSeconds' => 2,
                'signal' => ['type' => 'light-effect', 'effect' => 'okay'],
                'repeat' => ['everySeconds' => 4, 'times' => 84, 'while' => self::TEST_MODE_OFF],
            ],
        ],
    ];

    private const array SMOKE_ALERT = [
        'id' => 'smoke',
        'title' => 'SMOKE ALARM',
        'message' => 'Smoke alarm in the {area}',
        'policy' => 'smoke-leak',
        'rule' => ['type' => 'state', 'entities' => ['binary_sensor.smoke_detector_kitchen_smoke']],
    ];

    private const array FLOOD_ALERT = [
        'id' => 'kitchen-flood',
        'title' => 'Kitchen flood',
        'policy' => 'critical',
        'rule' => ['type' => 'state', 'entities' => ['binary_sensor.kitchen_leak'], 'forSeconds' => 5],
        'mitigations' => [['type' => 'turn-off', 'entity' => 'valve.main_water']],
    ];

    private HaFake $fake;
    private ServiceContainer $container;

    protected function setUp(): void
    {
        $this->fake = new HaFake($this);
        $this->container = new ServiceContainer(AlertConfigMapper::class, AlertMonitor::class);
        $this->container->replaceSynthetic(HaContext::class, $this->fake->ha);
        $this->container->replaceSynthetic(Scheduler::class, $this->fake->scheduler);
        $this->container->replaceSynthetic(LoggerInterface::class, new NullLogger());
    }

    public function testFloodClosesValveAndEscalates(): void
    {
        $this->fake->setState('binary_sensor.kitchen_leak', 'off');
        $this->createApp([self::FLOOD_ALERT])->initialize();

        $this->fake->setState('binary_sensor.kitchen_leak', 'on');
        $this->fake->advanceBy(Duration::seconds(5));
        $this->fake->advanceBy(Duration::seconds(120));

        self::assertSame(
            ['valve.close_valve', 'notify.mobile_app_phone', 'chime_tts.say'],
            array_column($this->fake->calls, 'service'),
        );
    }

    public function testDsmrPhaseLossOverMqttNotifies(): void
    {
        $this->createApp([[
            'id' => 'phase-loss',
            'title' => 'Phase down',
            'policy' => 'critical',
            'rule' => [
                'type' => 'below',
                'topics' => ['dsmr/reading/phase_voltage_l1', 'dsmr/reading/phase_voltage_l2', 'dsmr/reading/phase_voltage_l3'],
                'threshold' => 50,
                'unavailableIsFault' => true,
                'forSeconds' => 10,
            ],
        ]])->initialize();

        $this->fake->publishMqtt('dsmr/reading/phase_voltage_l1', '231.0');
        $this->fake->publishMqtt('dsmr/reading/phase_voltage_l2', '0.0');
        $this->fake->advanceBy(Duration::seconds(10));

        self::assertSame(['notify.mobile_app_phone'], array_column($this->fake->calls, 'service'));
        self::assertSame('dsmr/reading/phase_voltage_l2 is 0, below 50', $this->fake->calls[0]['data']['message'] ?? null);
    }

    public function testSmokeNotifiesSpeaksAndBlinksUntilAcknowledged(): void
    {
        $this->prepareSmokeScenario(theaterMode: 'off', testMode: 'off');

        $this->fake->setState('binary_sensor.smoke_detector_kitchen_smoke', 'on');
        $this->fake->advanceBy(Duration::seconds(10));

        $alert = $this->fake->calls[0];
        self::assertSame('SMOKE ALARM', $alert['data']['title'] ?? null);
        self::assertSame('Smoke alarm in the Kitchen', $alert['data']['message'] ?? null);
        self::assertSame(['text' => 1, 'tts' => 1, 'light' => 3], $this->countSmokeCalls());

        $this->fake->pressAction(($alert['data']['data']['tag'] ?? '') . ':ALERT_ACK');
        $this->fake->advanceBy(Duration::minutes(10));

        self::assertSame(['text' => 2, 'tts' => 1, 'light' => 3], $this->countSmokeCalls());
    }

    public function testSmokeRepeatsUpToConfiguredTimes(): void
    {
        $this->prepareSmokeScenario(theaterMode: 'off', testMode: 'off');

        $this->fake->setState('binary_sensor.smoke_detector_kitchen_smoke', 'on');
        $this->fake->advanceBy(Duration::minutes(10));

        self::assertSame(['text' => 1, 'tts' => 6, 'light' => 85], $this->countSmokeCalls());
    }

    public function testTheaterModeSilencesSpeechOnly(): void
    {
        $this->prepareSmokeScenario(theaterMode: 'on', testMode: 'off');

        $this->fake->setState('binary_sensor.smoke_detector_kitchen_smoke', 'on');
        $this->fake->advanceBy(Duration::seconds(10));

        self::assertSame(['text' => 1, 'tts' => 0, 'light' => 3], $this->countSmokeCalls());
    }

    public function testTestModeSendsEverythingOnce(): void
    {
        $this->prepareSmokeScenario(theaterMode: 'off', testMode: 'on');

        $this->fake->setState('binary_sensor.smoke_detector_kitchen_smoke', 'on');
        $this->fake->advanceBy(Duration::minutes(10));

        self::assertSame(['text' => 1, 'tts' => 1, 'light' => 1], $this->countSmokeCalls());
    }

    public function testDisposeStopsMonitoring(): void
    {
        $this->fake->setState('binary_sensor.kitchen_leak', 'off');
        $app = $this->createApp([self::FLOOD_ALERT]);
        $app->initialize();

        $app->dispose();
        $this->fake->setState('binary_sensor.kitchen_leak', 'on');
        $this->fake->advanceBy(Duration::minutes(5));

        self::assertSame([], $this->fake->calls);
    }

    public function testRejectsInvalidConfigAtConstruction(): void
    {
        $this->expectException(InvalidAlertConfig::class);

        $this->createApp([['id' => 'kitchen-flood', 'policy' => 'missing', 'rule' => self::FLOOD_ALERT['rule']]]);
    }

    private function prepareSmokeScenario(string $theaterMode, string $testMode): void
    {
        $this->fake->setState('input_boolean.alarm_theater_mode', $theaterMode);
        $this->fake->setState('input_boolean.alarm_test_mode', $testMode);
        $this->fake->placeInArea('binary_sensor.smoke_detector_kitchen_smoke', 'kitchen', 'Kitchen');
        $this->fake->placeInArea('light.kitchen_all', 'kitchen', 'Kitchen');
        $this->fake->setState('light.kitchen_all', 'off', ['effect_list' => ['okay']]);
        $this->fake->setState('binary_sensor.smoke_detector_kitchen_smoke', 'off');
        $this->createApp([self::SMOKE_ALERT])->initialize();
    }

    /** @return array{text: int, tts: int, light: int} */
    private function countSmokeCalls(): array
    {
        $counts = ['text' => 0, 'tts' => 0, 'light' => 0];

        foreach ($this->fake->calls as $call) {
            $kind = match (true) {
                $call['service'] === 'light.turn_on' => 'light',
                ($call['data']['message'] ?? null) === 'TTS' => 'tts',
                default => 'text',
            };
            $counts[$kind]++;
        }

        return $counts;
    }

    /** @param list<array<string, mixed>> $alerts */
    private function createApp(array $alerts): HomeAlertApp
    {
        return new HomeAlertApp(
            $this->container->getService(AlertConfigMapper::class),
            $this->container->getService(AlertMonitor::class),
            $this->fake->ha,
            $this->fake->scheduler,
            $this->fake->mqtt,
            $this->fake->clock,
            new NullLogger(),
            [...self::POLICIES, 'smoke-leak' => self::SMOKE_LEAK_POLICY],
            $alerts,
        );
    }
}
