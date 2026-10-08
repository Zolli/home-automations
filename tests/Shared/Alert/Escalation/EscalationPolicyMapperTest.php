<?php

declare(strict_types=1);

namespace App\Tests\Shared\Alert\Escalation;

use App\Tests\ServiceContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Alert\Config\InvalidAlertConfig;
use Shared\Alert\Escalation\EscalationPolicyMapper;
use Shared\Notification\DestinationType;
use Shared\Notification\Exception\InvalidNotificationPayload;
use Shared\Notification\Importance;

final class EscalationPolicyMapperTest extends TestCase
{
    private EscalationPolicyMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ServiceContainer(EscalationPolicyMapper::class)->getService(EscalationPolicyMapper::class);
    }

    public function testMapsPoliciesByName(): void
    {
        $policies = $this->mapper->mapToPolicies([
            'critical' => [
                'acknowledgeOnDismiss' => true,
                'steps' => [
                    ['destinations' => [['type' => 'notify_service', 'target' => 'notify.phone']]],
                    [
                        'afterSeconds' => 120,
                        'importance' => 'high',
                        'destinations' => [['type' => 'tts', 'target' => 'media_player.home']],
                        'repeat' => [
                            'everySeconds' => 30,
                            'times' => 5,
                            'while' => [['type' => 'eq', 'field' => 'input_boolean.alarm_test_mode', 'value' => 'off']],
                        ],
                    ],
                ],
            ],
            'warning' => ['steps' => [['destinations' => [['type' => 'notify_service', 'target' => 'notify.phone']]]]],
        ]);

        $critical = $policies->find('critical');
        $steps = $critical?->steps->listValues() ?? [];

        self::assertSame(0, $steps[0]->after->toMilliseconds());
        self::assertSame(Importance::Critical, $steps[0]->importance);
        self::assertSame(120_000, $steps[1]->after->toMilliseconds());
        self::assertSame(Importance::High, $steps[1]->importance);
        self::assertSame(DestinationType::Tts, $steps[1]->destinations->getFirst()?->type);
        self::assertNull($steps[0]->repetition);
        self::assertSame(30_000, $steps[1]->repetition?->every->toMilliseconds());
        self::assertSame(5, $steps[1]->repetition->maxTimes);
        self::assertSame('input_boolean.alarm_test_mode eq "off"', $steps[1]->repetition->while->describe());
        self::assertTrue($critical?->acknowledgeOnDismiss);
        self::assertFalse($policies->find('warning')?->acknowledgeOnDismiss);
    }

    public function testMapsSignalStep(): void
    {
        $policies = $this->mapper->mapToPolicies(['smoke-leak' => ['steps' => [
            ['afterSeconds' => 2, 'signal' => ['type' => 'light-effect', 'effect' => 'okay'], 'repeat' => ['everySeconds' => 4, 'times' => 84]],
        ]]]);

        $step = $policies->find('smoke-leak')?->steps->getFirst();

        self::assertSame('light effect okay in the fault area', $step?->signal?->describe());
        self::assertTrue($step->destinations->isEmpty());
        self::assertSame(84, $step->repetition?->maxTimes);
    }

    /** @return iterable<string, array{array<array-key, mixed>}> */
    public static function invalidConfigs(): iterable
    {
        $phone = [['type' => 'notify_service', 'target' => 'notify.phone']];

        yield 'missing steps' => [['critical' => ['acknowledgeOnDismiss' => true]]];
        yield 'missing destinations' => [['critical' => ['steps' => [['afterSeconds' => 0]]]]];
        yield 'unknown destination type' => [['critical' => ['steps' => [['destinations' => [['type' => 'sms', 'target' => 'x']]]]]]];
        yield 'unknown importance' => [['critical' => ['steps' => [['importance' => 'urgent', 'destinations' => $phone]]]]];
        yield 'negative delay' => [['critical' => ['steps' => [['afterSeconds' => -1, 'destinations' => $phone]]]]];
        yield 'non-boolean dismiss option' => [['critical' => ['acknowledgeOnDismiss' => 'yes', 'steps' => [['destinations' => $phone]]]]];
        yield 'unknown signal' => [['critical' => ['steps' => [['signal' => ['type' => 'siren']]]]]];
        yield 'signal without effect' => [['critical' => ['steps' => [['signal' => ['type' => 'light-effect']]]]]];
        yield 'repeat not a map' => [['critical' => ['steps' => [['destinations' => $phone, 'repeat' => 30]]]]];
        yield 'repeat without interval' => [['critical' => ['steps' => [['destinations' => $phone, 'repeat' => ['times' => 5]]]]]];
        yield 'non-integer repeat times' => [['critical' => ['steps' => [['destinations' => $phone, 'repeat' => ['everySeconds' => 30, 'times' => 'many']]]]]];
        yield 'unknown repeat condition' => [['critical' => ['steps' => [['destinations' => $phone, 'repeat' => ['everySeconds' => 30, 'while' => [['type' => 'between']]]]]]]];
        yield 'no steps' => [['critical' => ['steps' => []]]];
        yield 'unordered steps' => [['critical' => ['steps' => [['afterSeconds' => 60, 'destinations' => $phone], ['afterSeconds' => 0, 'destinations' => $phone]]]]];
        yield 'destinations and signal' => [['critical' => ['steps' => [['destinations' => $phone, 'signal' => ['type' => 'light-effect', 'effect' => 'okay']]]]]];
        yield 'zero repeat interval' => [['critical' => ['steps' => [['destinations' => $phone, 'repeat' => ['everySeconds' => 0]]]]]];
        yield 'zero repeat times' => [['critical' => ['steps' => [['destinations' => $phone, 'repeat' => ['everySeconds' => 30, 'times' => 0]]]]]];
    }

    /** @param array<array-key, mixed> $policies */
    #[DataProvider('invalidConfigs')]
    public function testRejectsInvalidConfig(array $policies): void
    {
        $this->expectException(InvalidAlertConfig::class);

        $this->mapper->mapToPolicies($policies);
    }

    public function testKeepsStepErrorAsCause(): void
    {
        try {
            $this->mapper->mapToPolicies(['critical' => ['steps' => [['afterSeconds' => 0]]]]);
            self::fail('Expected an invalid alert config.');
        } catch (InvalidAlertConfig $e) {
            self::assertInstanceOf(InvalidNotificationPayload::class, $e->getPrevious());
        }
    }
}
