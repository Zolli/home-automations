<?php

declare(strict_types=1);

namespace Shared\Notification;

use Psr\Log\LoggerInterface;
use Shared\Condition\Collection\FactCollection;
use Shared\Condition\ConditionContext;
use Shared\Notification\Action\ActionRouter;
use Shared\Notification\Collection\DestinationCollection;
use Shared\Notification\Exception\InvalidDestination;
use Shared\Notification\Sender\Collection\DestinationSenderCollection;
use Shared\Notification\Sender\DestinationSender;
use Stewart\Contracts\HaContext;
use Throwable;

final readonly class NotificationDispatcher implements Notifier
{
    public function __construct(
        private DestinationSenderCollection $senders,
        private HaContext $ha,
        private ActionRouter $actionRouter,
        private LoggerInterface $logger,
    ) {}

    public function send(Notification $notification): SentNotification
    {
        $this->validateDestinations($notification->destinations);

        $context = new ConditionContext($this->ha, FactCollection::keyedByClass([$notification->meta->importance]));
        $delivered = false;

        foreach ($notification->destinations as $destination) {
            $delivered = $this->sendToDestination($notification, $destination, $context) || $delivered;
        }

        return new SentNotification($notification, $this->actionRouter, $delivered);
    }

    private function sendToDestination(Notification $notification, Destination $destination, ConditionContext $context): bool
    {
        $log = [
            'id' => $notification->meta->id->value,
            'type' => $destination->type->value,
            'targets' => $destination->targets,
            'conditions' => $destination->condition->describe(),
        ];
        $delivered = false;

        try {
            if (!$destination->condition->isSatisfied($context)) {
                $this->logWhenDebugging($notification, 'Skipped destination, conditions not met', $log);

                return false;
            }

            foreach ($this->findSender($destination->type)->buildServiceCalls($notification, $destination) as $call) {
                $this->ha->callService($call->domain, $call->service, $call->data, $call->target);
                $delivered = true;
                $this->logWhenDebugging($notification, 'Sent', $log + ['call' => $call->toLogContext()]);
            }
        } catch (Throwable $e) {
            $this->logger->error('[NOTIFICATION] Delivery failed', $log + ['exception' => $e]);
        }

        return $delivered;
    }

    /** @throws InvalidDestination */
    private function validateDestinations(DestinationCollection $destinations): void
    {
        foreach ($destinations as $destination) {
            $this->findSender($destination->type)->validateDestination($destination);
        }
    }

    /** @throws InvalidDestination */
    private function findSender(DestinationType $type): DestinationSender
    {
        return $this->senders->find($type) ?? throw InvalidDestination::forMissingSender($type);
    }

    /** @param array<string, mixed> $context */
    private function logWhenDebugging(Notification $notification, string $message, array $context): void
    {
        if ($notification->meta->debug) {
            $this->logger->info("[NOTIFICATION] {$message}", $context);
        }
    }
}
