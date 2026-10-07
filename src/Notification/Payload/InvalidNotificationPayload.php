<?php

declare(strict_types=1);

namespace Shared\Notification\Payload;

use Shared\Notification\Payload\Collection\PayloadViolationCollection;
use UnexpectedValueException;

final class InvalidNotificationPayload extends UnexpectedValueException
{
    public static function forViolations(PayloadViolationCollection $violations): self
    {
        $reasons = $violations->mapToList(static fn(PayloadViolation $violation) => $violation->toString());

        return new self('Invalid notification payload: ' . implode('; ', $reasons));
    }

    public static function forViolation(PayloadViolation $violation): self
    {
        return self::forViolations(PayloadViolationCollection::fromViolations([$violation]));
    }
}
