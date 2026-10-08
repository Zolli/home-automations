<?php

declare(strict_types=1);

namespace Shared\Alert\Escalation;

use Closure;
use Psr\Log\LoggerInterface;
use Shared\Alert\Rule\Fault;
use Shared\Notification\Action\ActionListener;
use Shared\Notification\Action\NotificationAction;
use Shared\Notification\Action\NotificationButton;
use Shared\Notification\Action\NotificationDismissal;
use Shared\Notification\Collection\DestinationCollection;
use Shared\Notification\Destination;
use Shared\Notification\Exception\InvalidDestination;
use Shared\Notification\Importance;
use Shared\Notification\NotificationBuilder;
use Shared\Notification\NotificationId;
use Shared\Notification\Notifier;
use Shared\Notification\SentNotification;
use Stewart\Contracts\HaContext;
use Stewart\Contracts\Schedule\ScheduledTask;
use Stewart\Contracts\Schedule\Scheduler;
use Stewart\Contracts\Time\Duration;
use Throwable;

final class Escalation
{
    public const string ACKNOWLEDGE_ACTION = 'ALERT_ACK';

    /** @var list<ScheduledTask> */
    private array $tasks = [];

    /** @var list<StepRepeater> */
    private array $repeaters = [];

    /** @var list<ActionListener> */
    private array $listeners = [];

    private DestinationCollection $notifiedDestinations;

    /** @param Closure(Acknowledgement): void $whenAcknowledged */
    public function __construct(
        private readonly EscalationPolicy $policy,
        private readonly NotificationBuilder $message,
        private readonly Fault $fault,
        private readonly Closure $whenAcknowledged,
        private readonly Notifier $notifier,
        private readonly Scheduler $scheduler,
        private readonly HaContext $ha,
        private readonly LoggerInterface $logger,
    ) {
        $this->notifiedDestinations = DestinationCollection::empty();
    }

    public function startEscalating(): void
    {
        foreach ($this->policy->steps as $step) {
            if ($step->after->isLongerThan(Duration::zero())) {
                $this->tasks[] = $this->scheduler->runAfter($step->after, fn() => $this->sendStep($step));
            } else {
                $this->sendStep($step);
            }
        }
    }

    public function stopEscalating(): void
    {
        foreach ($this->tasks as $task) {
            $task->cancel();
        }

        foreach ($this->repeaters as $repeater) {
            $repeater->stopRepeating();
        }

        foreach ($this->listeners as $listener) {
            $listener->stopListening();
        }

        $this->tasks = [];
        $this->repeaters = [];
        $this->listeners = [];
    }

    public function sendFollowUp(string $body): void
    {
        if ($this->notifiedDestinations->isEmpty()) {
            return;
        }

        $this->sendNotification(
            $this->message
                ->withBody($body)
                ->withImportance(Importance::High)
                ->withAppendedDestinations(...$this->notifiedDestinations),
        );
    }

    private function sendStep(EscalationStep $step): void
    {
        $this->performStep($step);
        $this->rememberNotified($step->destinations);

        if ($step->repetition !== null) {
            $repeater = new StepRepeater($step->repetition, fn() => $this->repeatStep($step), $this->scheduler, $this->ha);
            $repeater->startRepeating();
            $this->repeaters[] = $repeater;
        }
    }

    private function repeatStep(EscalationStep $step): void
    {
        $this->listeners = array_values(array_filter($this->listeners, static fn(ActionListener $listener) => $listener->isListening()));
        $this->performStep($step);
    }

    private function performStep(EscalationStep $step): void
    {
        if ($step->signal === null) {
            $this->sendStepNotification($step);

            return;
        }

        try {
            $step->signal->emit($this->fault, $this->ha);
        } catch (Throwable $e) {
            $this->logger->error('[ALERT] Signal failed', ['policy' => $this->policy->name, 'signal' => $step->signal->describe(), 'exception' => $e]);
        }
    }

    private function sendStepNotification(EscalationStep $step): void
    {
        $sent = $this->sendNotification(
            $this->message
                ->withId(NotificationId::generate())
                ->withImportance($step->importance)
                ->withAppendedDestinations(...$step->destinations)
                ->withAppendedButtons(new NotificationButton(self::ACKNOWLEDGE_ACTION, 'Acknowledge')),
        );

        if ($sent === null) {
            return;
        }

        $listener = $sent->listenForActions($this->ha)->onAction(
            self::ACKNOWLEDGE_ACTION,
            fn(NotificationAction $action) => $this->acknowledge(Acknowledgement::fromAction($action)),
        );

        if ($this->policy->acknowledgeOnDismiss) {
            $listener->onDismissed(fn(NotificationDismissal $dismissal) => $this->acknowledge(Acknowledgement::fromDismissal($dismissal)));
        }

        $this->listeners[] = $listener;
    }

    private function sendNotification(NotificationBuilder $message): ?SentNotification
    {
        try {
            return $this->notifier->send($message->buildNotification());
        } catch (InvalidDestination $e) {
            $this->logger->error('[ALERT] Escalation notification not sent', ['policy' => $this->policy->name, 'exception' => $e]);

            return null;
        }
    }

    private function acknowledge(Acknowledgement $acknowledgement): void
    {
        $this->stopEscalating();
        ($this->whenAcknowledged)($acknowledgement);
    }

    private function rememberNotified(DestinationCollection $destinations): void
    {
        $this->notifiedDestinations = $this->notifiedDestinations->withAppendedDestinations(
            ...$destinations->filter(static fn(Destination $destination): bool => !$destination->type->isSpoken()),
        );
    }
}
