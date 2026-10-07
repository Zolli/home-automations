<?php

declare(strict_types=1);

namespace Shared\Notification\Collection;

use Shared\Notification\Destination;
use Stewart\Contracts\Collection\ListCollection;

/** @extends ListCollection<Destination> */
final readonly class DestinationCollection extends ListCollection
{
    /** @param iterable<Destination> $destinations */
    public static function fromDestinations(iterable $destinations): self
    {
        return self::fromList($destinations);
    }

    public function withAppendedDestinations(Destination ...$destinations): self
    {
        return self::fromDestinations([...$this, ...$destinations]);
    }
}
