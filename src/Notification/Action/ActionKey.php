<?php

declare(strict_types=1);

namespace Shared\Notification\Action;

use Shared\Notification\NotificationId;

final readonly class ActionKey
{
    private const string SEPARATOR = ':';

    private function __construct(
        public NotificationId $notificationId,
        public string $action,
    ) {}

    public static function forButton(NotificationId $notificationId, NotificationButton $button): self
    {
        return new self($notificationId, $button->action);
    }

    public static function tryFromString(string $key): ?self
    {
        $parts = explode(self::SEPARATOR, $key, 2);

        if (\count($parts) !== 2 || $parts[1] === '') {
            return null;
        }

        $notificationId = NotificationId::tryFromString($parts[0]);

        return $notificationId === null ? null : new self($notificationId, $parts[1]);
    }

    public function toString(): string
    {
        return $this->notificationId->value . self::SEPARATOR . $this->action;
    }
}
