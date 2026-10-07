<?php

declare(strict_types=1);

namespace Shared\Notification;

use Shared\Notification\Action\ActionListener;
use Shared\Notification\Action\ActionRouter;
use Stewart\Contracts\HaContext;

final readonly class SentNotification
{
    public function __construct(
        public Notification $notification,
        private ActionRouter $router,
        private bool $delivered,
    ) {}

    public function wasDelivered(): bool
    {
        return $this->delivered;
    }

    public function getNotificationId(): NotificationId
    {
        return $this->notification->meta->id;
    }

    public function listenForActions(HaContext $appContext): ActionListener
    {
        return $this->delivered
            ? $this->router->listenFor($this->getNotificationId(), $appContext)
            : $this->router->createStoppedListener($this->getNotificationId());
    }
}
