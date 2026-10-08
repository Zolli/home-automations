<?php

declare(strict_types=1);

namespace Shared\Notification\Exception;

use LogicException;
use Shared\Notification\DestinationType;

final class InvalidDestinationSenders extends LogicException
{
    public static function forDuplicateType(DestinationType $type): self
    {
        return new self("Multiple senders are registered for destination type \"{$type->value}\".");
    }
}
