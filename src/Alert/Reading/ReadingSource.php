<?php

declare(strict_types=1);

namespace Shared\Alert\Reading;

use Stewart\Contracts\Entity\EntityId;

final readonly class ReadingSource
{
    private function __construct(
        public ReadingSourceType $type,
        public string $key,
        public ?EntityId $entityId = null,
    ) {}

    public static function forEntity(EntityId $entityId): self
    {
        return new self(ReadingSourceType::Entity, $entityId->value, $entityId);
    }

    public static function forTopic(string $topic): self
    {
        return new self(ReadingSourceType::MqttTopic, $topic);
    }
}
