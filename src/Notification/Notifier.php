<?php

declare(strict_types=1);

namespace Shared\Notification;

use Shared\Notification\Exception\InvalidDestination;

interface Notifier
{
    /** @throws InvalidDestination */
    public function send(Notification $notification): SentNotification;
}
