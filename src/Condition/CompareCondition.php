<?php

declare(strict_types=1);

namespace Shared\Condition;

use Stewart\Contracts\Entity\EntityId;

final readonly class CompareCondition implements Condition
{
    /** @throws InvalidCondition */
    public function __construct(
        public EntityId $entityId,
        public Operator $operator,
        public mixed $value,
        public ?string $attribute = null,
    ) {
        if ($operator->requiresNumericValue() && !is_numeric($value)) {
            throw InvalidCondition::forNonNumericValue($operator, $value);
        }
    }

    public static function equals(EntityId|string $entityId, mixed $value, ?string $attribute = null): self
    {
        return new self(EntityId::fromStringOrId($entityId), Operator::Eq, $value, $attribute);
    }

    public static function notEquals(EntityId|string $entityId, mixed $value, ?string $attribute = null): self
    {
        return new self(EntityId::fromStringOrId($entityId), Operator::Neq, $value, $attribute);
    }

    public static function greaterThan(EntityId|string $entityId, int|float $value, ?string $attribute = null): self
    {
        return new self(EntityId::fromStringOrId($entityId), Operator::Gt, $value, $attribute);
    }

    public static function atLeast(EntityId|string $entityId, int|float $value, ?string $attribute = null): self
    {
        return new self(EntityId::fromStringOrId($entityId), Operator::Gte, $value, $attribute);
    }

    public static function lessThan(EntityId|string $entityId, int|float $value, ?string $attribute = null): self
    {
        return new self(EntityId::fromStringOrId($entityId), Operator::Lt, $value, $attribute);
    }

    public static function atMost(EntityId|string $entityId, int|float $value, ?string $attribute = null): self
    {
        return new self(EntityId::fromStringOrId($entityId), Operator::Lte, $value, $attribute);
    }

    public function isSatisfied(ConditionContext $context): bool
    {
        $state = $context->ha->getState($this->entityId);

        if ($state === null) {
            return false;
        }

        $actual = $this->attribute === null ? $state->state : $state->getAttribute($this->attribute);

        return $this->operator->compare($actual, $this->value);
    }

    public function describe(): string
    {
        $subject = $this->attribute === null ? $this->entityId->value : "{$this->entityId->value}[{$this->attribute}]";

        return \sprintf('%s %s %s', $subject, $this->operator->value, self::formatValue($this->value));
    }

    public static function formatValue(mixed $value): string
    {
        return json_encode($value, \JSON_PARTIAL_OUTPUT_ON_ERROR) ?: get_debug_type($value);
    }
}
