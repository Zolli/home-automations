<?php

declare(strict_types=1);

namespace Shared\Notification;

use InvalidArgumentException;

final class InvalidDestination extends InvalidArgumentException
{
    public static function forMissingTargets(DestinationType $type): self
    {
        return new self("Destination \"{$type->value}\" needs at least one target.");
    }

    public static function forMissingSender(DestinationType $type): self
    {
        return new self("Destination \"{$type->value}\" has no registered sender.");
    }

    public static function forInvalidTarget(DestinationType $type, string $target): self
    {
        return new self("Destination \"{$type->value}\" cannot send to \"{$target}\".");
    }
}
