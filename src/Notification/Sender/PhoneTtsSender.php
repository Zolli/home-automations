<?php

declare(strict_types=1);

namespace Shared\Notification\Sender;

use Shared\Notification\Destination;
use Shared\Notification\DestinationType;
use Shared\Notification\Exception\InvalidDestination;
use Shared\Notification\Notification;
use Shared\Notification\Sender\Collection\ServiceCallCollection;
use Stewart\Contracts\Entity\EntityId;

final readonly class PhoneTtsSender implements DestinationSender
{
    private const string NOTIFY_DOMAIN = 'notify';
    private const string TTS_COMMAND = 'TTS';
    private const string DEFAULT_MEDIA_STREAM = 'alarm_stream_max';

    /**
     * @param string|list<string> $phoneServices
     * @param array<string, mixed> $options
     */
    public static function createDestination(string|array $phoneServices, array $options = []): Destination
    {
        return new Destination(DestinationType::PhoneTts, $phoneServices, options: $options);
    }

    public function getSupportedType(): DestinationType
    {
        return DestinationType::PhoneTts;
    }

    public function validateDestination(Destination $destination): void
    {
        foreach ($destination->targets as $target) {
            if (EntityId::tryFromString($target)?->domain !== self::NOTIFY_DOMAIN) {
                throw InvalidDestination::forInvalidTarget($destination->type, $target);
            }
        }
    }

    public function buildServiceCalls(Notification $notification, Destination $destination): ServiceCallCollection
    {
        $data = [
            'message' => self::TTS_COMMAND,
            'data' => ['tts_text' => $notification->message->body, 'media_stream' => self::DEFAULT_MEDIA_STREAM, ...$destination->options],
        ];

        return ServiceCallCollection::fromServiceCalls(array_map(
            static fn(string $target) => new ServiceCall(self::NOTIFY_DOMAIN, new EntityId($target)->objectId, $data),
            $destination->targets,
        ));
    }
}
