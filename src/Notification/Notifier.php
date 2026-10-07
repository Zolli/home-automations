<?php

declare(strict_types=1);

namespace Shared\Notification;

interface Notifier
{
    /** @throws InvalidDestination */
    public function send(Notification $notification): SentNotification;
}
