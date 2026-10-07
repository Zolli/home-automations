<?php

declare(strict_types=1);

namespace Shared\Notification;

use Shared\Notification\Action\Collection\NotificationButtonCollection;
use Shared\Notification\Action\NotificationButton;
use Shared\Notification\Collection\DestinationCollection;

final readonly class NotificationBuilder
{
    /** @param array<string, mixed> $additionalData */
    private function __construct(
        private string $body,
        private ?string $title,
        private array $additionalData,
        private NotificationButtonCollection $buttons,
        private Importance $importance,
        private bool $debug,
        private ?NotificationId $id,
        private DestinationCollection $destinations,
    ) {}

    public static function create(): self
    {
        return new self(
            body: '',
            title: null,
            additionalData: [],
            buttons: NotificationButtonCollection::empty(),
            importance: Importance::DEFAULT,
            debug: false,
            id: null,
            destinations: DestinationCollection::empty(),
        );
    }

    public function withBody(string $body): self
    {
        return clone($this, ['body' => $body]);
    }

    public function withTitle(?string $title): self
    {
        return clone($this, ['title' => $title]);
    }

    /** @param array<string, mixed> $data */
    public function withServiceData(array $data): self
    {
        return $this->withAdditionalData('data', $data);
    }

    public function withAdditionalData(string $key, mixed $value): self
    {
        return clone($this, ['additionalData' => ServiceData::mergeReplacingLists($this->additionalData, [$key => $value])]);
    }

    public function withAppendedButtons(NotificationButton ...$buttons): self
    {
        return clone($this, ['buttons' => $this->buttons->withAppendedButtons(...$buttons)]);
    }

    public function withImportance(Importance $importance): self
    {
        return clone($this, ['importance' => $importance]);
    }

    public function withDebug(bool $enabled = true): self
    {
        return clone($this, ['debug' => $enabled]);
    }

    public function withId(NotificationId $id): self
    {
        return clone($this, ['id' => $id]);
    }

    public function withAppendedDestinations(Destination ...$destinations): self
    {
        return clone($this, ['destinations' => $this->destinations->withAppendedDestinations(...$destinations)]);
    }

    public function buildNotification(): Notification
    {
        return new Notification(
            new Message($this->body, $this->title, $this->additionalData, $this->buttons),
            $this->destinations,
            new Meta($this->importance, $this->debug, $this->id),
        );
    }
}
