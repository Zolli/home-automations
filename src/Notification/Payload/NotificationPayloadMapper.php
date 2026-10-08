<?php

declare(strict_types=1);

namespace Shared\Notification\Payload;

use Shared\Notification\Action\Collection\NotificationButtonCollection;
use Shared\Notification\Action\NotificationButton;
use Shared\Notification\Exception\InvalidMessage;
use Shared\Notification\Exception\InvalidNotification;
use Shared\Notification\Exception\InvalidNotificationButton;
use Shared\Notification\Exception\InvalidNotificationPayload;
use Shared\Notification\Importance;
use Shared\Notification\Message;
use Shared\Notification\Meta;
use Shared\Notification\Notification;
use Shared\Notification\NotificationId;

/**
 * @phpstan-type ButtonPayload array{action: string, title: string, ...<string, mixed>}
 * @phpstan-type MessagePayload array{body: string, title?: string, additionalData?: array<string, mixed>, buttons?: list<ButtonPayload>}
 * @phpstan-type MetaPayload array{importance?: string, debug?: bool, id?: string, replyTopic?: string}
 * @phpstan-type Payload array{message: MessagePayload, destinations: list<mixed>, meta?: MetaPayload}
 */
final readonly class NotificationPayloadMapper
{
    public function __construct(
        private NotificationPayloadSchema $schema,
        private DestinationPayloadMapper $destinations,
    ) {}

    /** @throws InvalidNotificationPayload */
    public function mapToNotificationRequest(mixed $payload): NotificationRequest
    {
        $this->schema->validatePayload($payload);

        return $this->mapValidatedPayload($payload);
    }

    /**
     * @param Payload $payload
     * @throws InvalidNotificationPayload
     */
    private function mapValidatedPayload(array $payload): NotificationRequest
    {
        $meta = $payload['meta'] ?? [];
        $notification = new Notification(
            $this->mapMessage($payload['message']),
            $this->destinations->mapToDestinations($payload['destinations'], ['destinations']),
            $this->mapMeta($meta),
        );

        return new NotificationRequest($notification, $meta['replyTopic'] ?? null);
    }

    /**
     * @param MessagePayload $message
     * @throws InvalidNotificationPayload
     */
    private function mapMessage(array $message): Message
    {
        $buttons = $this->mapButtons($message['buttons'] ?? []);

        try {
            return new Message($message['body'], $message['title'] ?? null, $message['additionalData'] ?? [], $buttons);
        } catch (InvalidMessage $e) {
            throw InvalidNotificationPayload::forViolation(PayloadViolation::atPath(['message'], $e->getMessage()));
        }
    }

    /**
     * @param list<ButtonPayload> $buttons
     * @throws InvalidNotificationPayload
     */
    private function mapButtons(array $buttons): NotificationButtonCollection
    {
        $mapped = [];

        foreach ($buttons as $index => $button) {
            $options = array_diff_key($button, array_flip(NotificationButton::RESERVED_OPTIONS));

            try {
                $mapped[] = new NotificationButton($button['action'], $button['title'], $options);
            } catch (InvalidNotificationButton $e) {
                throw InvalidNotificationPayload::forViolation(
                    PayloadViolation::atPath(['message', 'buttons', $index], $e->getMessage()),
                );
            }
        }

        return NotificationButtonCollection::fromButtons($mapped);
    }

    /**
     * @param MetaPayload $meta
     * @throws InvalidNotificationPayload
     */
    private function mapMeta(array $meta): Meta
    {
        return new Meta(
            isset($meta['importance']) ? $this->mapImportance($meta['importance']) : Importance::DEFAULT,
            $meta['debug'] ?? false,
            isset($meta['id']) ? $this->mapNotificationId($meta['id']) : null,
        );
    }

    /** @throws InvalidNotificationPayload */
    private function mapImportance(string $level): Importance
    {
        $importance = Importance::tryFrom($level);

        if ($importance === null) {
            $knownLevels = array_map(static fn(Importance $importance) => $importance->value, Importance::cases());
            $reason = \sprintf('Importance "%s" is unknown, expected one of: %s.', $level, implode(', ', $knownLevels));

            throw InvalidNotificationPayload::forViolation(PayloadViolation::atPath(['meta', 'importance'], $reason));
        }

        return $importance;
    }

    /** @throws InvalidNotificationPayload */
    private function mapNotificationId(string $id): NotificationId
    {
        try {
            return NotificationId::fromString($id);
        } catch (InvalidNotification $e) {
            throw InvalidNotificationPayload::forViolation(PayloadViolation::atPath(['meta', 'id'], $e->getMessage()));
        }
    }
}
