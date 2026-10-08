<?php

declare(strict_types=1);

namespace Shared\Notification\Sender;

use Shared\Notification\Destination;
use Shared\Notification\DestinationType;
use Shared\Notification\Exception\InvalidDestination;
use Shared\Notification\Notification;
use Shared\Notification\Sender\Collection\ServiceCallCollection;
use Stewart\Contracts\Entity\EntityId;
use Stewart\Contracts\Service\ServiceTarget;

final readonly class ChimeTtsSender implements DestinationSender
{
    private const string SPEAKER_DOMAIN = 'media_player';
    private const string SERVICE_DOMAIN = 'chime_tts';
    private const string SERVICE = 'say';

    /**
     * @param string|list<string> $speakers
     * @param array<string, mixed> $options
     */
    public static function createDestination(string|array $speakers, array $options = []): Destination
    {
        return new Destination(DestinationType::Tts, $speakers, options: $options);
    }

    public function getSupportedType(): DestinationType
    {
        return DestinationType::Tts;
    }

    public function validateDestination(Destination $destination): void
    {
        foreach ($destination->targets as $target) {
            if (EntityId::tryFromString($target)?->domain !== self::SPEAKER_DOMAIN) {
                throw InvalidDestination::forInvalidTarget($destination->type, $target);
            }
        }
    }

    public function buildServiceCalls(Notification $notification, Destination $destination): ServiceCallCollection
    {
        return ServiceCallCollection::fromServiceCalls([new ServiceCall(
            self::SERVICE_DOMAIN,
            self::SERVICE,
            ['message' => $notification->message->body, ...$destination->options],
            ServiceTarget::forEntities(...$destination->targets),
        )]);
    }
}
