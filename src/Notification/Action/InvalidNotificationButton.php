<?php

declare(strict_types=1);

namespace Shared\Notification\Action;

use InvalidArgumentException;

final class InvalidNotificationButton extends InvalidArgumentException
{
    public static function forEmptyAction(): self
    {
        return new self('Notification button action must not be empty.');
    }

    public static function forReservedOption(string $option): self
    {
        return new self("Notification button option \"{$option}\" is set by the button itself.");
    }
}
