<?php

declare(strict_types=1);

namespace Shared\Notification;

use Shared\Notification\Collection\DestinationCollection;

final readonly class Notification
{
    public function __construct(
        public Message $message,
        public DestinationCollection $destinations,
        public Meta $meta = new Meta(),
    ) {
        if ($destinations->isEmpty()) {
            throw InvalidNotification::forMissingDestinations();
        }
    }
}
