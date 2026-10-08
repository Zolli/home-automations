<?php

declare(strict_types=1);

namespace Shared\Notification\Action;

use Psr\Log\LoggerInterface;
use Shared\Notification\NotificationId;
use Stewart\Contracts\Event\HaEvent;
use Stewart\Contracts\HaContext;
use Stewart\Contracts\Schedule\Scheduler;
use Stewart\Contracts\Selector\Selector;
use Stewart\Contracts\Subscription;
use Stewart\Contracts\Time\Duration;

final class ActionRouter
{
    public const string ACTION_EVENT = 'mobile_app_notification_action';

    public const string CLEARED_EVENT = 'mobile_app_notification_cleared';

    private const int LISTENING_TTL_HOURS = 2;

    /** @var array<string, ListeningNotification> */
    private array $listening = [];

    /** @var array<int, Subscription> */
    private array $subscriptionsByAppContext = [];

    public function __construct(
        private readonly Scheduler $scheduler,
        private readonly LoggerInterface $logger,
    ) {}

    public function listenFor(NotificationId $id, HaContext $appContext): ActionListener
    {
        ($this->listening[$id->value] ?? null)?->listener->stopListening();

        $listener = new ActionListener($id, $this->logger, $this->release(...));
        $this->subscriptionsByAppContext[spl_object_id($appContext)] ??= $appContext
            ->watchEvents(Selector::anyOf(self::ACTION_EVENT, self::CLEARED_EVENT))
            ->subscribe(fn(HaEvent $event) => $this->whenEventReceived($appContext, $event));
        $this->listening[$id->value] = new ListeningNotification(
            $listener,
            $appContext,
            $this->scheduler->runAfter(Duration::hours(self::LISTENING_TTL_HOURS), $listener->stopListening(...)),
        );

        return $listener;
    }

    public function createStoppedListener(NotificationId $id): ActionListener
    {
        return new ActionListener($id, $this->logger, $this->release(...), listening: false);
    }

    private function release(ActionListener $listener): void
    {
        $listening = $this->listening[$listener->notificationId->value] ?? null;

        if ($listening?->listener !== $listener) {
            return;
        }

        unset($this->listening[$listener->notificationId->value]);
        $listening->cancelExpiry();
        $this->unsubscribeWhenUnused($listening->appContext);
    }

    private function unsubscribeWhenUnused(HaContext $appContext): void
    {
        foreach ($this->listening as $listening) {
            if ($listening->isOwnedBy($appContext)) {
                return;
            }
        }

        $this->subscriptionsByAppContext[spl_object_id($appContext)]->unsubscribe();
        unset($this->subscriptionsByAppContext[spl_object_id($appContext)]);
    }

    private function whenEventReceived(HaContext $appContext, HaEvent $event): void
    {
        match ($event->type) {
            self::ACTION_EVENT => $this->whenActionPressed($appContext, $event),
            self::CLEARED_EVENT => $this->whenNotificationCleared($appContext, $event),
            default => null,
        };
    }

    private function whenActionPressed(HaContext $appContext, HaEvent $event): void
    {
        $value = $event->getValue('action');
        $key = \is_string($value) ? ActionKey::tryFromString($value) : null;

        if ($key === null) {
            return;
        }

        $this->findOwnedListener($key->notificationId, $appContext)?->dispatchAction(
            new NotificationAction($key->notificationId, $key->action, $event->data, $event->context?->userId),
        );
    }

    private function whenNotificationCleared(HaContext $appContext, HaEvent $event): void
    {
        $nestedData = $event->getValue('data');
        $tag = $event->getValue('tag') ?? (\is_array($nestedData) ? $nestedData['tag'] ?? null : null);
        $notificationId = \is_string($tag) ? NotificationId::tryFromString($tag) : null;

        if ($notificationId === null) {
            return;
        }

        $this->findOwnedListener($notificationId, $appContext)?->dispatchDismissal(
            new NotificationDismissal($notificationId, $event->data, $event->context?->userId),
        );
    }

    private function findOwnedListener(NotificationId $id, HaContext $appContext): ?ActionListener
    {
        $listening = $this->listening[$id->value] ?? null;

        return $listening?->isOwnedBy($appContext) ? $listening->listener : null;
    }
}
