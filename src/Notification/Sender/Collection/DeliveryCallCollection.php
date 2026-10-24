<?php

declare(strict_types=1);

namespace Shared\Notification\Sender\Collection;

use Shared\Notification\Sender\DeliveryCall;
use Stewart\Contracts\Collection\ListCollection;

/** @extends ListCollection<DeliveryCall> */
final readonly class DeliveryCallCollection extends ListCollection
{
    /** @param iterable<DeliveryCall> $deliveryCalls */
    public static function fromDeliveryCalls(iterable $deliveryCalls): self
    {
        return self::fromList($deliveryCalls);
    }
}
