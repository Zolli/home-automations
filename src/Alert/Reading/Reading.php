<?php

declare(strict_types=1);

namespace Shared\Alert\Reading;

use Stewart\Contracts\State\EntityState;
use Stewart\Contracts\Time\Instant;

final readonly class Reading
{
    private const array UNAVAILABLE_VALUES = [EntityState::UNAVAILABLE, EntityState::UNKNOWN];

    /** @param array<string, mixed> $attributes */
    public function __construct(
        public ReadingSource $source,
        public string $label,
        public string $value,
        public array $attributes = [],
        public ?Instant $receivedAt = null,
    ) {}

    public static function fromEntityState(EntityState $state): self
    {
        return new self(ReadingSource::forEntity($state->entityId), $state->getFriendlyName(), $state->state, $state->attributes);
    }

    public static function fromMqttPayload(string $topic, string $payload, Instant $receivedAt): self
    {
        return new self(ReadingSource::forTopic($topic), $topic, trim($payload), receivedAt: $receivedAt);
    }

    public function readNumber(?string $attribute = null): ?float
    {
        $value = $attribute === null ? $this->value : ($this->attributes[$attribute] ?? null);

        return is_numeric($value) ? (float) $value : null;
    }

    public function isUnavailable(): bool
    {
        return \in_array($this->value, self::UNAVAILABLE_VALUES, true);
    }
}
