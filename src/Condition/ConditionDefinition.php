<?php

declare(strict_types=1);

namespace Shared\Condition;

use Stewart\Contracts\Entity\EntityId;

final readonly class ConditionDefinition
{
    /** @param array<array-key, mixed> $fields */
    private function __construct(
        public string $type,
        private array $fields,
    ) {}

    /**
     * @param array<array-key, mixed> $fields
     * @throws InvalidCondition
     */
    public static function fromArray(array $fields): self
    {
        $type = $fields['type'] ?? null;

        if (!\is_string($type)) {
            throw InvalidCondition::forMissingType();
        }

        return new self($type, $fields);
    }

    /** @throws InvalidCondition */
    public function requireEntityId(): EntityId
    {
        $field = $this->fields['field'] ?? null;
        $entityId = \is_string($field) ? EntityId::tryFromString($field) : null;

        return $entityId ?? throw InvalidCondition::forMissingEntityId($this->type);
    }

    /** @throws InvalidCondition */
    public function requireValue(): mixed
    {
        if (!\array_key_exists('value', $this->fields)) {
            throw InvalidCondition::forMissingValue($this->type);
        }

        return $this->fields['value'];
    }

    /** @throws InvalidCondition */
    public function findAttribute(): ?string
    {
        $attribute = $this->fields['attribute'] ?? null;

        if ($attribute !== null && !\is_string($attribute)) {
            throw InvalidCondition::forNonStringAttribute($this->type);
        }

        return $attribute;
    }
}
