<?php

declare(strict_types=1);

namespace App\Tests\Alert;

use App\Alert\HomeAlertApp;
use App\Tests\ServiceContainer;
use App\Tests\Shared\Alert\AlertFixture;
use PHPUnit\Framework\TestCase;
use Shared\Alert\AlertMonitor;
use Shared\Alert\Config\AlertConfigMapper;
use Shared\Alert\Config\InvalidAlertConfig;
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
    private const array TEST_MODE_OFF = [
        ['type' => 'eq', 'field' => 'input_boolean.alarm_test_mode', 'value' => 'off'],
    ];
    private const array SMOKE_LEAK_POLICY = [
        'steps' => [
            ['destinations' => [['type' => 'notify_service', 'target' => 'notify.mobile_app_phone']]],
            [
                'destinations' => [[
                    'type'       => 'phone_tts',
                    'target'     => 'notify.mobile_app_phone',
                    'conditions' => [['type' => 'eq', 'field' => 'input_boolean.alarm_theater_mode', 'value' => 'off']],
                ]],
                'repeat'       => ['everySeconds' => 30, 'times' => 5, 'while' => self::TEST_MODE_OFF],
            ],
            [
                'afterSeconds' => 2,
                'signal'       => ['type' => 'light-effect', 'effect' => 'okay'],
                'repeat'       => ['everySeconds' => 4, 'times' => 84, 'while' => self::TEST_MODE_OFF],
            ],
        ],
    ];
    private const array SMOKE_ALERT = [
        'id'      => 'smoke',
        'title'   => 'SMOKE ALARM',
        'message' => 'Smoke alarm in the {area}',
        'policy'  => 'smoke-leak',
        'rule'    => ['type' => 'state', 'entities' => ['binary_sensor.smoke_detector_kitchen_smoke']],
    ];
    private const array FLOOD_ALERT = [
        'id'          => 'kitchen-flood',
        'title'       => 'Kitchen flood',
        'policy'      => 'critical',
        'rule'        => ['type' => 'state', 'entities' => ['binary_sensor.kitchen_leak'], 'forSeconds' => 5],
        'mitigations' => [['type' => 'turn-off', 'entity' => 'valve.main_water']],
    ];

    private AlertFixture $fixture;

    protected function setUp(): void
    {
        $this->fixture = new AlertFixture();
        $harness       = $this->fixture->harness;
        $container     = new ServiceContainer(AlertConfigMapper::class, AlertMonitor::class);
        $container->replaceSyntheticWithFakes($harness);
        $harness->provideService(AlertConfigMapper::class, $container->getService(AlertConfigMapper::class));
        $harness->provideService(AlertMonitor::class, $container->getService(AlertMonitor::class));
    }

    public function testFloodClosesValveAndEscalates(): void
    {
        $this->fixture->ha->pushState('binary_sensor.kitchen_leak', 'off');
        $this->startApp([self::FLOOD_ALERT]);

        $this->fixture->ha->pushState('binary_sensor.kitchen_leak', 'on');
        $this->fixture->advanceTime(Duration::seconds(5));
        $this->fixture->advanceTime(Duration::seconds(120));

        self::assertSame(
            ['valve.close_valve', 'notify.mobile_app_phone', 'chime_tts.say'],
            $this->fixture->listCalledServices(),
        );
    }

    public function testDsmrPhaseLossOverMqttNotifies(): void
    {
        $this->startApp([[
            'id'     => 'phase-loss',
            'title'  => 'Phase down',
            'policy' => 'critical',
            'rule'   => [
                'type'               => 'below',
                'topics'             => [
                    'dsmr/reading/phase_voltage_l1',
                    'dsmr/reading/phase_voltage_l2',
                    'dsmr/reading/phase_voltage_l3',
                ],
                'threshold'          => 50,
                'unavailableIsFault' => true,
                'forSeconds'         => 10,
            ],
        ]]);

        $this->fixture->publishMqtt('dsmr/reading/phase_voltage_l1', '231.0');
        $this->fixture->publishMqtt('dsmr/reading/phase_voltage_l2', '0.0');
        $this->fixture->advanceTime(Duration::seconds(10));

        self::assertSame(['notify.mobile_app_phone'], $this->fixture->listCalledServices());
        self::assertSame(
            'dsmr/reading/phase_voltage_l2 is 0, below 50',
            $this->fixture->ha->getLastCall()->data['message'] ?? null,
        );
    }

    public function testSmokeNotifiesSpeaksAndBlinksUntilAcknowledged(): void
    {
        $this->prepareSmokeScenario(theaterMode: 'off', testMode: 'off');

        $this->fixture->ha->pushState('binary_sensor.smoke_detector_kitchen_smoke', 'on');
        $this->fixture->advanceTime(Duration::seconds(10));

        $alert = $this->fixture->ha->calls->getFirst()->data ?? [];
        self::assertSame('SMOKE ALARM', $alert['title'] ?? null);
        self::assertSame('Smoke alarm in the Kitchen', $alert['message'] ?? null);
        self::assertSame(['text' => 1, 'tts' => 1, 'light' => 3], $this->countSmokeCalls());

        $this->fixture->pressAction(($alert['data']['tag'] ?? '') . ':ALERT_ACK');
        $this->fixture->advanceTime(Duration::minutes(10));

        self::assertSame(['text' => 2, 'tts' => 1, 'light' => 3], $this->countSmokeCalls());
    }

    public function testSmokeRepeatsUpToConfiguredTimes(): void
    {
        $this->prepareSmokeScenario(theaterMode: 'off', testMode: 'off');

        $this->fixture->ha->pushState('binary_sensor.smoke_detector_kitchen_smoke', 'on');
        $this->fixture->advanceTime(Duration::minutes(10));

        self::assertSame(['text' => 1, 'tts' => 6, 'light' => 85], $this->countSmokeCalls());
    }

    public function testTheaterModeSilencesSpeechOnly(): void
    {
        $this->prepareSmokeScenario(theaterMode: 'on', testMode: 'off');

        $this->fixture->ha->pushState('binary_sensor.smoke_detector_kitchen_smoke', 'on');
        $this->fixture->advanceTime(Duration::seconds(10));

        self::assertSame(['text' => 1, 'tts' => 0, 'light' => 3], $this->countSmokeCalls());
    }

    public function testTestModeSendsEverythingOnce(): void
    {
        $this->prepareSmokeScenario(theaterMode: 'off', testMode: 'on');

        $this->fixture->ha->pushState('binary_sensor.smoke_detector_kitchen_smoke', 'on');
        $this->fixture->advanceTime(Duration::minutes(10));

        self::assertSame(['text' => 1, 'tts' => 1, 'light' => 1], $this->countSmokeCalls());
    }

    public function testDisposeStopsMonitoring(): void
    {
        $this->fixture->ha->pushState('binary_sensor.kitchen_leak', 'off');
        $this->startApp([self::FLOOD_ALERT]);

        $this->fixture->harness->stopApp();
        $this->fixture->ha->pushState('binary_sensor.kitchen_leak', 'on');
        $this->fixture->advanceTime(Duration::minutes(5));

        self::assertTrue($this->fixture->ha->calls->isEmpty());
    }

    public function testRejectsInvalidConfigAtConstruction(): void
    {
        $this->expectException(InvalidAlertConfig::class);

        $this->startApp([['id' => 'kitchen-flood', 'policy' => 'missing', 'rule' => self::FLOOD_ALERT['rule']]]);
    }

    private function prepareSmokeScenario(string $theaterMode, string $testMode): void
    {
        $this->fixture->ha->pushState('input_boolean.alarm_theater_mode', $theaterMode);
        $this->fixture->ha->pushState('input_boolean.alarm_test_mode', $testMode);
        $this->fixture->placeInArea('binary_sensor.smoke_detector_kitchen_smoke', 'kitchen', 'Kitchen');
        $this->fixture->placeInArea('light.kitchen_all', 'kitchen', 'Kitchen');
        $this->fixture->ha->pushState('light.kitchen_all', 'off', ['effect_list' => ['okay']]);
        $this->fixture->ha->pushState('binary_sensor.smoke_detector_kitchen_smoke', 'off');
        $this->startApp([self::SMOKE_ALERT]);
    }

    /** @return array{text: int, tts: int, light: int} */
    private function countSmokeCalls(): array
    {
        $counts = ['text' => 0, 'tts' => 0, 'light' => 0];

        foreach ($this->fixture->ha->calls as $call) {
            $kind = match (true) {
                $call->getServiceName() === 'light.turn_on' => 'light',
                ($call->data['message'] ?? null) === 'TTS'  => 'tts',
                default                                     => 'text',
            };
            $counts[$kind]++;
        }

        return $counts;
    }

    /** @param list<array<string, mixed>> $alerts */
    private function startApp(array $alerts): void
    {
        $this->fixture->harness->startApp(HomeAlertApp::class, [
            'policies' => [...self::POLICIES, 'smoke-leak' => self::SMOKE_LEAK_POLICY],
            'alerts'   => $alerts,
        ]);
    }
}
