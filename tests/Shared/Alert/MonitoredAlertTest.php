<?php

declare(strict_types=1);

namespace App\Tests\Shared\Alert;

use App\Tests\Notification\NotifierRecorder;
use App\Tests\Shared\Alert\Rule\ReadingFixture;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use RuntimeException;
use Shared\Alert\AlertDefinition;
use Shared\Alert\AlertState;
use Shared\Alert\FaultLocator;
use Shared\Alert\Escalation\Collection\EscalationStepCollection;
use Shared\Alert\Escalation\Escalation;
use Shared\Alert\Escalation\EscalationPolicy;
use Shared\Alert\Escalation\EscalationStep;
use Shared\Alert\Escalation\StepRepetition;
use Shared\Alert\Mitigation\Collection\MitigationCollection;
use Shared\Alert\Mitigation\Mitigation;
use Shared\Alert\Mitigation\TurnOffMitigation;
use Shared\Alert\MonitoredAlert;
use Shared\Alert\Rule\StateMatchRule;
use Shared\Notification\Action\ActionRouter;
use Shared\Notification\Collection\DestinationCollection;
use Shared\Notification\Notification;
use Shared\Notification\Sender\NotifyServiceSender;
use Shared\Template\TextTemplate;
use Stewart\Contracts\Entity\EntityId;
use Stewart\Contracts\HaContext;
use Stewart\Contracts\Time\Duration;

final class MonitoredAlertTest extends TestCase
{
    private const string LEAK_SENSOR = 'binary_sensor.kitchen_leak';

    private AlertFixture $fixture;
    private NotifierRecorder $notifier;

    protected function setUp(): void
    {
        $this->fixture = new AlertFixture();
        $this->notifier = new NotifierRecorder(new ActionRouter($this->fixture->harness->scheduler, new NullLogger()));
        $this->fixture->ha->pushState(self::LEAK_SENSOR, 'off');
    }

    public function testRaiseMitigatesAndNotifies(): void
    {
        $this->startMonitoring(new TurnOffMitigation(new EntityId('valve.main_water')));

        $this->fixture->ha->pushState(self::LEAK_SENSOR, 'on');

        self::assertSame(['valve.close_valve'], $this->fixture->listCalledServices());
        self::assertSame(["binary_sensor.kitchen_leak is on\nClosed valve.main_water"], $this->listBodies());
        self::assertSame('Kitchen flood', $this->notifier->sent[0]->message->title);
    }

    public function testFailedMitigationStillNotifies(): void
    {
        $alert = $this->startMonitoring(new class implements Mitigation {
            public function mitigate(HaContext $ha): void
            {
                throw new RuntimeException('Valve offline');
            }

            public function describe(): string
            {
                return 'Close valve.main_water';
            }
        });

        $this->fixture->ha->pushState(self::LEAK_SENSOR, 'on');

        self::assertSame(AlertState::Raised, $alert->getState());
        self::assertSame(["binary_sensor.kitchen_leak is on\nFailed: Close valve.main_water"], $this->listBodies());
    }

    public function testAcknowledgeStopsRepeatsAndConfirms(): void
    {
        $alert = $this->startMonitoring();
        $this->fixture->ha->pushState(self::LEAK_SENSOR, 'on');

        $this->fixture->pressAction($this->notifier->sent[0]->meta->id->value . ':' . Escalation::ACKNOWLEDGE_ACTION);
        $this->fixture->advanceTime(Duration::hours(1));

        self::assertSame(AlertState::Acknowledged, $alert->getState());
        self::assertSame(['binary_sensor.kitchen_leak is on', 'Acknowledged.'], $this->listBodies());
    }

    public function testDismissalConfirmsAsDismissed(): void
    {
        $alert = $this->startMonitoringWithPolicy(true);
        $this->fixture->ha->pushState(self::LEAK_SENSOR, 'on');

        $this->fixture->dismissNotification($this->notifier->sent[0]->meta->id->value);

        self::assertSame(AlertState::Acknowledged, $alert->getState());
        self::assertSame(['binary_sensor.kitchen_leak is on', 'Dismissed.'], $this->listBodies());
    }

    public function testClearStopsEscalationAndConfirms(): void
    {
        $alert = $this->startMonitoring();
        $this->fixture->ha->pushState(self::LEAK_SENSOR, 'on');

        $this->fixture->ha->pushState(self::LEAK_SENSOR, 'off');
        $this->fixture->advanceTime(Duration::hours(1));

        self::assertSame(AlertState::Idle, $alert->getState());
        self::assertSame(['binary_sensor.kitchen_leak is on', 'Cleared.'], $this->listBodies());
        self::assertFalse($this->notifier->sent[1]->message->hasButtons());
    }

    public function testRaisesAgainAfterClear(): void
    {
        $this->startMonitoring();

        $this->fixture->ha->pushState(self::LEAK_SENSOR, 'on');
        $this->fixture->ha->pushState(self::LEAK_SENSOR, 'off');
        $this->fixture->ha->pushState(self::LEAK_SENSOR, 'on');

        self::assertSame(['binary_sensor.kitchen_leak is on', 'Cleared.', 'binary_sensor.kitchen_leak is on'], $this->listBodies());
    }

    public function testClearsAfterMitigationRecoversAndRaisesAgain(): void
    {
        $alert = $this->startMonitoring(new TurnOffMitigation(new EntityId(self::LEAK_SENSOR)));

        $this->fixture->ha->pushState(self::LEAK_SENSOR, 'on');
        $this->fixture->ha->pushState(self::LEAK_SENSOR, 'off');
        $this->fixture->ha->pushState(self::LEAK_SENSOR, 'on');
        $this->fixture->ha->pushState(self::LEAK_SENSOR, 'off');
        $this->fixture->advanceTime(Duration::hours(1));

        $raised = "binary_sensor.kitchen_leak is on\nTurned off binary_sensor.kitchen_leak";
        self::assertSame([$raised, 'Cleared.', $raised, 'Cleared.'], $this->listBodies());
        self::assertSame(['homeassistant.turn_off', 'homeassistant.turn_off'], $this->fixture->listCalledServices());
        self::assertSame(AlertState::Idle, $alert->getState());
        self::assertSame(0, $this->fixture->countPendingTimers());
    }

    public function testRendersMessageWithFaultArea(): void
    {
        $this->fixture->placeInArea(self::LEAK_SENSOR, 'kitchen', 'Kitchen');
        $this->startAlert(false, new TextTemplate('Water leak detected in the {area}'));

        $this->fixture->ha->pushState(self::LEAK_SENSOR, 'on');

        self::assertSame(['Water leak detected in the Kitchen'], $this->listBodies());
        self::assertSame('Kitchen flood', $this->notifier->sent[0]->message->title);
    }

    public function testFallsBackToLabelWithoutArea(): void
    {
        $this->startAlert(false, new TextTemplate('Water leak detected in the {area}'));

        $this->fixture->ha->pushState(self::LEAK_SENSOR, 'on', ['friendly_name' => 'Kitchen leak sensor']);

        self::assertSame(['Water leak detected in the Kitchen leak sensor'], $this->listBodies());
    }

    public function testStopMonitoringSilencesAlert(): void
    {
        $alert = $this->startMonitoring();
        $this->fixture->ha->pushState(self::LEAK_SENSOR, 'on');

        $alert->stopMonitoring();
        $this->fixture->ha->pushState(self::LEAK_SENSOR, 'off');
        $this->fixture->ha->pushState(self::LEAK_SENSOR, 'on');
        $this->fixture->advanceTime(Duration::hours(1));

        self::assertCount(1, $this->notifier->sent);
        self::assertSame(0, $this->fixture->countPendingTimers());
    }

    private function startMonitoring(Mitigation ...$mitigations): MonitoredAlert
    {
        return $this->startMonitoringWithPolicy(false, ...$mitigations);
    }

    private function startMonitoringWithPolicy(bool $acknowledgeOnDismiss, Mitigation ...$mitigations): MonitoredAlert
    {
        return $this->startAlert($acknowledgeOnDismiss, new TextTemplate('{reason}'), array_values($mitigations));
    }

    /** @param list<Mitigation> $mitigations */
    private function startAlert(bool $acknowledgeOnDismiss, TextTemplate $message, array $mitigations = []): MonitoredAlert
    {
        $policy = new EscalationPolicy(
            'critical',
            EscalationStepCollection::fromSteps([
                new EscalationStep(
                    Duration::zero(),
                    DestinationCollection::fromDestinations([NotifyServiceSender::createDestination('notify.phone')]),
                    repetition: new StepRepetition(Duration::minutes(5)),
                ),
            ]),
            $acknowledgeOnDismiss,
        );
        $definition = new AlertDefinition(
            'kitchen-flood',
            new TextTemplate('Kitchen flood'),
            $message,
            new StateMatchRule(ReadingFixture::createEntitySources(self::LEAK_SENSOR), 'on', Duration::zero()),
            $policy,
            MitigationCollection::fromMitigations($mitigations),
        );
        $alert = new MonitoredAlert($definition, $this->fixture->createMonitoringContext(), $this->notifier, new FaultLocator(), new NullLogger());
        $alert->startMonitoring();

        return $alert;
    }

    /** @return list<string> */
    private function listBodies(): array
    {
        return array_map(static fn(Notification $notification): string => $notification->message->body, $this->notifier->sent);
    }
}
