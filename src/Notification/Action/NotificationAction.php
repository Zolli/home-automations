<?php

declare(strict_types=1);

namespace Shared\Notification\Action;

use Shared\Notification\NotificationId;

final readonly class NotificationAction
{
    /** @param array<string, mixed> $eventData */
    public function __construct(
        public NotificationId $notificationId,
        public string $action,
        public array $eventData = [],
        public ?string $userId = null,
    ) {}

    public function getReplyText(): ?string
    {
        $text = $this->eventData['reply_text'] ?? null;

        return \is_string($text) ? $text : null;
    }
}
