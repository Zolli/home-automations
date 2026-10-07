<?php

declare(strict_types=1);

namespace Shared\Notification\Sender\Collection;

use Shared\Notification\Sender\ServiceCall;
use Stewart\Contracts\Collection\ListCollection;

/** @extends ListCollection<ServiceCall> */
final readonly class ServiceCallCollection extends ListCollection
{
    /** @param iterable<ServiceCall> $serviceCalls */
    public static function fromServiceCalls(iterable $serviceCalls): self
    {
        return self::fromList($serviceCalls);
    }
}
