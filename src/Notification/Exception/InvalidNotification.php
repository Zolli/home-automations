<?php

declare(strict_types=1);

namespace Shared\Notification\Exception;

use InvalidArgumentException;

final class InvalidNotification extends InvalidArgumentException
{
    public static function forMissingDestinations(): self
    {
        return new self('Notification needs at least one destination.');
    }

    public static function forDisallowedIdCharacters(string $id): self
    {
        return new self("Notification id may only contain letters, digits, \"_\" and \"-\", \"{$id}\" given.");
    }
}
