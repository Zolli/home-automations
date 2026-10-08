<?php

declare(strict_types=1);

namespace Shared\Notification\Exception;

use InvalidArgumentException;

final class InvalidMessage extends InvalidArgumentException
{
    public static function forEmptyBody(): self
    {
        return new self('Notification body must not be empty.');
    }

    public static function forRawActions(): self
    {
        return new self('Notification "data.actions" must be given as buttons.');
    }
}
