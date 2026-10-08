<?php

declare(strict_types=1);

namespace Shared\Notification\Action;

use Shared\Notification\Exception\InvalidNotificationButton;

final readonly class NotificationButton
{
    public const string URI_ACTION = 'URI';

    public const array RESERVED_OPTIONS = ['action', 'title'];

    /**
     * @param array<string, mixed> $options
     * @throws InvalidNotificationButton
     */
    public function __construct(
        public string $action,
        public string $title,
        public array $options = [],
    ) {
        if (trim($action) === '') {
            throw InvalidNotificationButton::forEmptyAction();
        }

        foreach (self::RESERVED_OPTIONS as $reserved) {
            if (\array_key_exists($reserved, $options)) {
                throw InvalidNotificationButton::forReservedOption($reserved);
            }
        }
    }

    public function isRoutable(): bool
    {
        return $this->action !== self::URI_ACTION;
    }
}
