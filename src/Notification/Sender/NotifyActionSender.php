<?php

declare(strict_types=1);

namespace Shared\Notification\Sender;

use Shared\Notification\Action\ActionKey;
use Shared\Notification\Action\NotificationButton;
use Shared\Notification\DeliveryData;
use Shared\Notification\Destination;
use Shared\Notification\DestinationType;
use Shared\Notification\Exception\InvalidDestination;
use Shared\Notification\Notification;
use Shared\Notification\NotificationId;
use Shared\Notification\Sender\Collection\DeliveryCallCollection;
use Stewart\Contracts\Action\ActionName;
use Stewart\Contracts\Entity\EntityId;

final readonly class NotifyActionSender implements DestinationSender
{
    private const string NOTIFY_DOMAIN = 'notify';

    /** @param array<string, mixed> $options */
    public static function createDestination(string $action, array $options = []): Destination
    {
        return new Destination(DestinationType::NotifyAction, $action, options: $options);
    }

    public function getSupportedType(): DestinationType
    {
        return DestinationType::NotifyAction;
    }

    public function validateDestination(Destination $destination): void
    {
        foreach ($destination->targets as $target) {
            if (EntityId::tryFromString($target)?->domain !== self::NOTIFY_DOMAIN) {
                throw InvalidDestination::forInvalidTarget($destination->type, $target);
            }
        }
    }

    public function buildDeliveryCalls(Notification $notification, Destination $destination): DeliveryCallCollection
    {
        $data = DeliveryData::mergeReplacingLists(
            array_filter([
                'message' => $notification->message->body,
                'title'   => $notification->message->title,
            ], static fn(mixed $value) => $value !== null),
            $notification->message->additionalData,
            $destination->options,
        );

        if ($notification->message->hasButtons()) {
            $platformData            = \is_array($data['data'] ?? null) ? $data['data'] : [];
            $platformData['actions'] = $notification->message->buttons->mapToList(
                fn(NotificationButton $button) => $this->mapButton($button, $notification->meta->id),
            );
            $platformData['tag'] ??= $notification->meta->id->value;
            $data['data'] = $platformData;
        }

        return DeliveryCallCollection::fromDeliveryCalls(array_map(
            static fn(string $target) => new DeliveryCall(new ActionName($target), $data),
            $destination->targets,
        ));
    }

    /** @return array<string, mixed> */
    private function mapButton(NotificationButton $button, NotificationId $notificationId): array
    {
        $action = $button->isRoutable() ? ActionKey::forButton($notificationId, $button)->toString() : $button->action;

        return ['action' => $action, 'title' => $button->title, ...$button->options];
    }
}
