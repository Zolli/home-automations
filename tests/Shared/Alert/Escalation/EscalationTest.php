<?php

declare(strict_types=1);

namespace App\Tests\Shared\Alert\Escalation;

use App\Tests\Notification\NotifierRecorder;
use App\Tests\Shared\Alert\HaFake;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Shared\Alert\Escalation\AcknowledgeMethod;
use Shared\Alert\Escalation\Acknowledgement;
use Shared\Alert\Escalation\Collection\EscalationStepCollection;
use Shared\Alert\Escalation\Escalation;
use Shared\Alert\Escalation\EscalationPolicy;
use Shared\Alert\Escalation\EscalationStep;
use Shared\Alert\Escalation\StepRepetition;
use Shared\Alert\Reading\ReadingSource;
use Shared\Alert\Rule\Fault;
use Shared\Condition\CompareCondition;
use Shared\Notification\Action\ActionRouter;
use Shared\Notification\Collection\DestinationCollection;
use Shared\Notification\Importance;
use Shared\Notification\Notification;
use Shared\Notification\NotificationBuilder;
use Shared\Notification\Sender\ChimeTtsSender;
use Shared\Notification\Sender\NotifyServiceSender;
use Shared\Notification\Sender\PhoneTtsSender;
use Stewart\Contracts\Entity\EntityId;
use Stewart\Contracts\Time\Duration;

final class EscalationTest extends TestCase
{
    private HaFake $fake;
    private NotifierRecorder $notifier;

    /** @var list<Acknowledgement> */
    private array $acknowledged = [];

    protected function setUp(): void
    {
        $this->fake = new HaFake($this);
        $this->notifier = new NotifierRecorder(new ActionRouter($this->fake->scheduler, new NullLogger()));
    }

    public function testSendsStepsOnSchedule(): void
    {
        $this->startEscalation();

        $this->fake->advanceBy(Duration::seconds(119));
        self::assertSame(['notify.phone'], $this->listSentTargets());

        $this->fake->advanceBy(Duration::seconds(1));
        self::assertSame(['notify.phone', 'media_player.home'], $this->listSentTargets());

        $first = $this->notifier->sent[0];
        self::assertSame('Kitchen flood', $first->message->title);
        self::assertSame(Importance::Critical, $first->meta->importance);
        self::assertSame([Escalation::ACKNOWLEDGE_ACTION], $first->message->buttons->mapToList(static fn($button) => $button->action));
    }

    public function testRepeatsLastStepUntilAcknowledged(): void
    {
        $this->startEscalation();
        $this->fake->advanceBy(Duration::seconds(420));
        self::assertCount(3, $this->notifier->sent);

        $this->pressAcknowledgeOn($this->notifier->sent[2]);
        $this->fake->advanceBy(Duration::hours(1));

        self::assertCount(3, $this->notifier->sent);
        self::assertCount(1, $this->acknowledged);
        self::assertSame(AcknowledgeMethod::Button, $this->acknowledged[0]->method);
        self::assertSame('user-1', $this->acknowledged[0]->userId);
    }

    public function testAcknowledgeOnEarlierStepStopsEscalation(): void
    {
        $this->startEscalation();

        $this->pressAcknowledgeOn($this->notifier->sent[0]);
        $this->fake->advanceBy(Duration::hours(1));

        self::assertSame(['notify.phone'], $this->listSentTargets());
        self::assertSame(0, $this->fake->countPendingTasks());
    }

    public function testDismissalAcknowledgesWhenPolicyAllows(): void
    {
        $this->startEscalation(acknowledgeOnDismiss: true);

        $this->fake->dismissNotification($this->notifier->sent[0]->meta->id->value);
        $this->fake->advanceBy(Duration::hours(1));

        self::assertSame(['notify.phone'], $this->listSentTargets());
        self::assertSame(AcknowledgeMethod::Dismissal, $this->acknowledged[0]->method ?? null);
    }

    public function testDismissalIgnoredByDefault(): void
    {
        $this->startEscalation();

        $this->fake->dismissNotification($this->notifier->sent[0]->meta->id->value);
        $this->fake->advanceBy(Duration::seconds(120));

        self::assertSame(['notify.phone', 'media_player.home'], $this->listSentTargets());
        self::assertSame([], $this->acknowledged);
    }

    public function testStopEscalatingCancelsPendingSteps(): void
    {
        $escalation = $this->startEscalation();

        $escalation->stopEscalating();
        $this->fake->advanceBy(Duration::hours(1));

        self::assertSame(['notify.phone'], $this->listSentTargets());
        self::assertSame(0, $this->fake->countPendingTasks());
        self::assertSame([], $this->acknowledged);
    }

    public function testFollowUpGoesOnlyToNotifiedDestinations(): void
    {
        $escalation = $this->startEscalation();
        $escalation->stopEscalating();

        $escalation->sendFollowUp('Cleared');

        $followUp = $this->notifier->sent[1];
        self::assertSame(['notify.phone'], $followUp->destinations->mapToList(static fn($destination) => $destination->targets[0]));
        self::assertSame('Kitchen flood', $followUp->message->title);
        self::assertSame('Cleared', $followUp->message->body);
        self::assertSame(Importance::High, $followUp->meta->importance);
        self::assertFalse($followUp->message->hasButtons());
    }

    public function testStopsRepeatingAfterMaxTimes(): void
    {
        $this->createEscalation($this->createRepeatingPolicy(new StepRepetition(Duration::seconds(30), maxTimes: 5)))->startEscalating();

        $this->fake->advanceBy(Duration::hours(1));

        self::assertCount(6, $this->notifier->sent);
    }

    public function testStopsRepeatingOnceConditionFails(): void
    {
        $this->fake->setState('input_boolean.alarm_test_mode', 'off');
        $repetition = new StepRepetition(Duration::seconds(30), while: CompareCondition::equals('input_boolean.alarm_test_mode', 'off'));
        $this->createEscalation($this->createRepeatingPolicy($repetition))->startEscalating();
        $this->fake->advanceBy(Duration::seconds(60));

        $this->fake->setState('input_boolean.alarm_test_mode', 'on');
        $this->fake->advanceBy(Duration::hours(1));

        self::assertCount(3, $this->notifier->sent);
    }

    public function testFollowUpSkipsSpokenDestinations(): void
    {
        $escalation = $this->startEscalation();
        $this->fake->advanceBy(Duration::seconds(120));

        $escalation->sendFollowUp('Cleared');

        self::assertSame(['notify.phone'], $this->notifier->sent[2]->destinations->mapToList(static fn($destination) => $destination->targets[0]));
    }

    public function testRepeatsSignalStepUntilAcknowledged(): void
    {
        $signal = new SignalRecorder();
        $this->createEscalation(new EscalationPolicy('signalling', EscalationStepCollection::fromSteps([
            new EscalationStep(Duration::zero(), DestinationCollection::fromDestinations([NotifyServiceSender::createDestination('notify.phone')])),
            EscalationStep::forSignal(Duration::seconds(2), $signal, new StepRepetition(Duration::seconds(4), maxTimes: 84)),
        ])))->startEscalating();
        $this->fake->advanceBy(Duration::seconds(10));

        $this->pressAcknowledgeOn($this->notifier->sent[0]);
        $this->fake->advanceBy(Duration::minutes(10));

        self::assertSame(['binary_sensor.kitchen_leak', 'binary_sensor.kitchen_leak', 'binary_sensor.kitchen_leak'], $signal->emittedFor);
    }

    public function testFailingSignalDoesNotStopEscalation(): void
    {
        $signal = new SignalRecorder(failing: true);
        $this->createEscalation(new EscalationPolicy('signalling', EscalationStepCollection::fromSteps([
            EscalationStep::forSignal(Duration::zero(), $signal),
            new EscalationStep(Duration::seconds(5), DestinationCollection::fromDestinations([NotifyServiceSender::createDestination('notify.phone')])),
        ])))->startEscalating();

        $this->fake->advanceBy(Duration::seconds(5));

        self::assertCount(1, $this->notifier->sent);
    }

    public function testSkipsFollowUpBeforeAnyStepSent(): void
    {
        $policy = new EscalationPolicy('delayed', EscalationStepCollection::fromSteps([
            new EscalationStep(Duration::seconds(60), DestinationCollection::fromDestinations([NotifyServiceSender::createDestination('notify.phone')])),
        ]));
        $escalation = $this->createEscalation($policy);
        $escalation->startEscalating();

        $escalation->sendFollowUp('Cleared');

        self::assertSame([], $this->notifier->sent);
    }

    private function startEscalation(bool $acknowledgeOnDismiss = false): Escalation
    {
        $escalation = $this->createEscalation(new EscalationPolicy(
            'critical',
            EscalationStepCollection::fromSteps([
                new EscalationStep(Duration::zero(), DestinationCollection::fromDestinations([NotifyServiceSender::createDestination('notify.phone')])),
                new EscalationStep(
                    Duration::seconds(120),
                    DestinationCollection::fromDestinations([ChimeTtsSender::createDestination('media_player.home')]),
                    repetition: new StepRepetition(Duration::seconds(300)),
                ),
            ]),
            $acknowledgeOnDismiss,
        ));
        $escalation->startEscalating();

        return $escalation;
    }

    private function createRepeatingPolicy(StepRepetition $repetition): EscalationPolicy
    {
        return new EscalationPolicy('repeating', EscalationStepCollection::fromSteps([
            new EscalationStep(
                Duration::zero(),
                DestinationCollection::fromDestinations([PhoneTtsSender::createDestination('notify.phone')]),
                repetition: $repetition,
            ),
        ]));
    }

    private function createEscalation(EscalationPolicy $policy): Escalation
    {
        return new Escalation(
            $policy,
            NotificationBuilder::create()->withTitle('Kitchen flood')->withBody('Leak sensor is on'),
            new Fault(ReadingSource::forEntity(new EntityId('binary_sensor.kitchen_leak')), 'Leak sensor', 'Leak sensor is on'),
            function (Acknowledgement $acknowledgement): void {
                $this->acknowledged[] = $acknowledgement;
            },
            $this->notifier,
            $this->fake->scheduler,
            $this->fake->ha,
            new NullLogger(),
        );
    }

    private function pressAcknowledgeOn(Notification $notification): void
    {
        $this->fake->pressAction($notification->meta->id->value . ':' . Escalation::ACKNOWLEDGE_ACTION);
    }

    /** @return list<?string> */
    private function listSentTargets(): array
    {
        return array_map(static fn(Notification $notification) => $notification->destinations->getFirst()?->targets[0], $this->notifier->sent);
    }
}
