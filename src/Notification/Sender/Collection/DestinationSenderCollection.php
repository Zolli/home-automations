<?php

declare(strict_types=1);

namespace Shared\Notification\Sender\Collection;

use Shared\Notification\DestinationType;
use Shared\Notification\Sender\DestinationSender;
use Stewart\Contracts\Collection\KeyedCollection;

/** @extends KeyedCollection<string, DestinationSender> */
final readonly class DestinationSenderCollection extends KeyedCollection
{
    /**
     * @param iterable<DestinationSender> $senders
     * @throws InvalidDestinationSenders
     */
    public static function keyedByDestinationType(iterable $senders): self
    {
        $sendersByType = [];

        foreach ($senders as $sender) {
            $type = $sender->getSupportedType();

            if (\array_key_exists($type->value, $sendersByType)) {
                throw InvalidDestinationSenders::forDuplicateType($type);
            }

            $sendersByType[$type->value] = $sender;
        }

        return self::fromElementsByKey($sendersByType);
    }

    public function find(DestinationType $type): ?DestinationSender
    {
        return $this->elementAt($type->value);
    }
}
