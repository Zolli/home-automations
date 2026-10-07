<?php

declare(strict_types=1);

namespace Shared\Condition;

use InvalidArgumentException;

final class InvalidCondition extends InvalidArgumentException
{
    public static function forMissingType(): self
    {
        return new self('Condition needs a "type".');
    }

    /** @param list<string> $knownTypes */
    public static function forUnknownType(string $type, array $knownTypes): self
    {
        return new self(\sprintf('Condition type "%s" is unknown, expected one of: %s.', $type, implode(', ', $knownTypes)));
    }

    public static function forMissingEntityId(string $type): self
    {
        return new self("Condition \"{$type}\" needs a \"field\" with an entity id.");
    }

    public static function forMissingValue(string $type): self
    {
        return new self("Condition \"{$type}\" needs a \"value\".");
    }

    public static function forNonStringAttribute(string $type): self
    {
        return new self("Condition \"{$type}\" needs a string \"attribute\".");
    }

    public static function forNonNumericValue(Operator $operator, mixed $value): self
    {
        return new self(\sprintf('Condition "%s" needs a numeric "value", %s given.', $operator->value, CompareCondition::formatValue($value)));
    }
}
