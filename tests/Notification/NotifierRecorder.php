<?php

declare(strict_types=1);

namespace App\Tests\Notification;

use Shared\Notification\Action\ActionRouter;
use Shared\Notification\Exception\InvalidDestination;
use Shared\Notification\Notification;
use Shared\Notification\Notifier;
use Shared\Notification\SentNotification;

final class NotifierRecorder implements Notifier
{
    /** @var list<Notification> */
    public array $sent = [];

    private bool $delivers = true;

    private ?InvalidDestination $rejection = null;

    public function __construct(private readonly ActionRouter $router) {}

    public function send(Notification $notification): SentNotification
    {
        if ($this->rejection !== null) {
            throw $this->rejection;
        }

        $this->sent[] = $notification;

        return new SentNotification($notification, $this->router, $this->delivers);
    }

    public function stopDelivering(): void
    {
        $this->delivers = false;
    }

    public function rejectDestinations(InvalidDestination $rejection): void
    {
        $this->rejection = $rejection;
    }
}
