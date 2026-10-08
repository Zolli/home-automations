<?php

declare(strict_types=1);

namespace App\Tests\Shared\Alert\Config;

use App\Tests\ServiceContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Alert\Config\AlertConfigMapper;
use Shared\Alert\Config\InvalidAlertConfig;
use Shared\Alert\Rule\ThresholdRule;

final class AlertConfigMapperTest extends TestCase
{
    private const array POLICIES = [
        'critical' => ['steps' => [['destinations' => [['type' => 'notify_service', 'target' => 'notify.phone']]]]],
    ];

    private AlertConfigMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ServiceContainer(AlertConfigMapper::class)->getService(AlertConfigMapper::class);
    }

    public function testMapsAlertsWithPolicyRuleAndMitigations(): void
    {
        $definitions = $this->mapper->mapToDefinitions(self::POLICIES, [
            [
                'id' => 'kitchen-flood',
                'title' => 'Kitchen flood',
                'message' => 'Water leak in the {area}',
                'policy' => 'critical',
                'rule' => ['type' => 'state', 'entities' => ['binary_sensor.kitchen_leak']],
                'mitigations' => [['type' => 'turn-off', 'entity' => 'valve.main_water']],
            ],
            [
                'id' => 'phase-loss',
                'policy' => 'critical',
                'rule' => ['type' => 'below', 'entities' => ['sensor.l1_voltage'], 'threshold' => 180],
            ],
        ]);

        $flood = $definitions->find('kitchen-flood');
        $phaseLoss = $definitions->find('phase-loss');

        self::assertSame('Kitchen flood', $flood?->title->text);
        self::assertSame('critical', $flood->policy->name);
        self::assertCount(1, $flood->mitigations);
        self::assertSame('Water leak in the {area}', $flood->message->text);
        self::assertSame('phase-loss', $phaseLoss?->title->text);
        self::assertSame('{reason}', $phaseLoss->message->text);
        self::assertInstanceOf(ThresholdRule::class, $phaseLoss->rule);
        self::assertTrue($phaseLoss->mitigations->isEmpty());
    }

    /** @return iterable<string, array{list<array<string, mixed>>}> */
    public static function invalidAlerts(): iterable
    {
        $rule = ['type' => 'state', 'entities' => ['binary_sensor.kitchen_leak']];

        yield 'missing id' => [[['policy' => 'critical', 'rule' => $rule]]];
        yield 'unknown policy' => [[['id' => 'flood', 'policy' => 'warning', 'rule' => $rule]]];
        yield 'missing rule' => [[['id' => 'flood', 'policy' => 'critical']]];
        yield 'invalid mitigation' => [[['id' => 'flood', 'policy' => 'critical', 'rule' => $rule, 'mitigations' => [['type' => 'siren']]]]];
        yield 'empty message' => [[['id' => 'flood', 'policy' => 'critical', 'rule' => $rule, 'message' => ' ']]];
        yield 'duplicate id' => [[['id' => 'flood', 'policy' => 'critical', 'rule' => $rule], ['id' => 'flood', 'policy' => 'critical', 'rule' => $rule]]];
    }

    /** @param list<array<string, mixed>> $alerts */
    #[DataProvider('invalidAlerts')]
    public function testRejectsInvalidAlert(array $alerts): void
    {
        $this->expectException(InvalidAlertConfig::class);

        $this->mapper->mapToDefinitions(self::POLICIES, $alerts);
    }
}
