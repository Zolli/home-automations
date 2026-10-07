<?php

declare(strict_types=1);

namespace Shared\Notification\Sender;

use Shared\Notification\Destination;
use Shared\Notification\DestinationType;
use Shared\Notification\InvalidDestination;
use Shared\Notification\Notification;
use Shared\Notification\Sender\Collection\ServiceCallCollection;

interface DestinationSender
{
    public function getSupportedType(): DestinationType;

    /** @throws InvalidDestination */
    public function validateDestination(Destination $destination): void;

    public function buildServiceCalls(Notification $notification, Destination $destination): ServiceCallCollection;
}
