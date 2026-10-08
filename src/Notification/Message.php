<?php

declare(strict_types=1);

namespace Shared\Notification;

use Shared\Notification\Action\Collection\NotificationButtonCollection;
use Shared\Notification\Exception\InvalidMessage;

final readonly class Message
{
    public NotificationButtonCollection $buttons;

    /** @param array<string, mixed> $additionalData */
    public function __construct(
        public string $body,
        public ?string $title = null,
        public array $additionalData = [],
        ?NotificationButtonCollection $buttons = null,
    ) {
        if (trim($body) === '') {
            throw InvalidMessage::forEmptyBody();
        }

        if (\is_array($additionalData['data'] ?? null) && \array_key_exists('actions', $additionalData['data'])) {
            throw InvalidMessage::forRawActions();
        }

        $this->buttons = $buttons ?? NotificationButtonCollection::empty();
    }

    public function hasButtons(): bool
    {
        return !$this->buttons->isEmpty();
    }
}
