<?php

declare(strict_types=1);

namespace Shared\Notification\Payload;

use Shared\Notification\Notification;

final readonly class NotificationRequest
{
    public function __construct(
        public Notification $notification,
        public ?string $replyTopic = null,
    ) {}
}
