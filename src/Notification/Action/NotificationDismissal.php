<?php

declare(strict_types=1);

namespace Shared\Notification\Action;

use Shared\Notification\NotificationId;

final readonly class NotificationDismissal
{
    /** @param array<string, mixed> $eventData */
    public function __construct(
        public NotificationId $notificationId,
        public array $eventData = [],
        public ?string $userId = null,
    ) {}
}
