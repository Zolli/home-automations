<?php

declare(strict_types=1);

namespace Shared\Notification\Payload\Collection;

use Shared\Notification\Payload\PayloadViolation;
use Stewart\Contracts\Collection\ListCollection;

/** @extends ListCollection<PayloadViolation> */
final readonly class PayloadViolationCollection extends ListCollection
{
    /** @param iterable<PayloadViolation> $violations */
    public static function fromViolations(iterable $violations): self
    {
        return self::fromList($violations);
    }
}
