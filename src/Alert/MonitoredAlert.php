<?php

declare(strict_types=1);

namespace Shared\Alert;

use Psr\Log\LoggerInterface;
use Shared\Alert\Escalation\AcknowledgeMethod;
use Shared\Alert\Escalation\Acknowledgement;
use Shared\Alert\Escalation\Escalation;
use Shared\Alert\Mitigation\Mitigation;
use Shared\Alert\Reading\ReadingFeed;
use Shared\Alert\Rule\Fault;
use Shared\Alert\Rule\FaultListener;
use Shared\Alert\Rule\FaultWatcher;
use Shared\Notification\NotificationBuilder;
use Shared\Notification\Notifier;
use Stewart\Contracts\Registry\Area;
use Throwable;

final class MonitoredAlert implements FaultListener
{
    private readonly FaultWatcher $watcher;

    private AlertState $state = AlertState::Idle;

    private ?Escalation $escalation = null;

    public function __construct(
        public readonly AlertDefinition $definition,
        private readonly MonitoringContext $context,
        private readonly Notifier $notifier,
        private readonly FaultLocator $locator,
        private readonly LoggerInterface $logger,
    ) {
        $this->watcher = new FaultWatcher($definition->rule, new ReadingFeed($definition->rule->listSources(), $context), $context->scheduler, $this);
    }

    public function startMonitoring(): void
    {
        $this->watcher->startWatching();
    }

    public function stopMonitoring(): void
    {
        $this->watcher->stopWatching();
        $this->escalation?->stopEscalating();
        $this->escalation = null;
        $this->state = AlertState::Idle;
    }

    public function getState(): AlertState
    {
        return $this->state;
    }

    public function raiseFault(Fault $fault): void
    {
        if ($this->state !== AlertState::Idle) {
            return;
        }

        $this->state = AlertState::Raised;
        $this->logger->warning("[ALERT] {$this->definition->id} raised", ['reason' => $fault->reason]);

        $variables = $fault->listTemplateVariables($this->findArea($fault));
        $body = implode("\n", [$this->definition->message->render($variables), ...$this->runMitigations()]);

        $this->escalation = new Escalation(
            $this->definition->policy,
            NotificationBuilder::create()->withTitle($this->definition->title->render($variables))->withBody($body),
            $fault,
            $this->acknowledge(...),
            $this->notifier,
            $this->context->scheduler,
            $this->context->ha,
            $this->logger,
        );
        $this->escalation->startEscalating();
    }

    public function clearFault(): void
    {
        if ($this->state === AlertState::Idle) {
            return;
        }

        $this->logger->info("[ALERT] {$this->definition->id} cleared");
        $this->escalation?->stopEscalating();
        $this->escalation?->sendFollowUp('Cleared.');
        $this->escalation = null;
        $this->state = AlertState::Idle;
    }

    private function acknowledge(Acknowledgement $acknowledgement): void
    {
        $this->state = AlertState::Acknowledged;
        $this->logger->info("[ALERT] {$this->definition->id} acknowledged", ['method' => $acknowledgement->method->value, 'user' => $acknowledgement->userId]);
        $this->escalation?->sendFollowUp($acknowledgement->method === AcknowledgeMethod::Dismissal ? 'Dismissed.' : 'Acknowledged.');
    }

    /** @return list<string> */
    private function runMitigations(): array
    {
        return $this->definition->mitigations->mapToList(function (Mitigation $mitigation): string {
            try {
                $mitigation->mitigate($this->context->ha);

                return $mitigation->describe();
            } catch (Throwable $e) {
                $this->logger->error("[ALERT] {$this->definition->id} mitigation failed", ['mitigation' => $mitigation->describe(), 'exception' => $e]);

                return "Failed: {$mitigation->describe()}";
            }
        });
    }

    private function findArea(Fault $fault): ?Area
    {
        try {
            return $this->locator->findArea($fault, $this->context->ha);
        } catch (Throwable $e) {
            $this->logger->warning("[ALERT] {$this->definition->id} area lookup failed", ['exception' => $e]);

            return null;
        }
    }
}
