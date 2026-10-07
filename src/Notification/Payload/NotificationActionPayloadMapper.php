<?php

declare(strict_types=1);

namespace Shared\Notification\Payload;

use Shared\Notification\Action\NotificationAction;

final readonly class NotificationActionPayloadMapper
{
    /** @return array<string, string> */
    public function mapToPayload(NotificationAction $action): array
    {
        return array_filter([
            'id' => $action->notificationId->value,
            'action' => $action->action,
            'replyText' => $action->getReplyText(),
            'userId' => $action->userId,
        ], static fn(?string $value) => $value !== null);
    }
}
