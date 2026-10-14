<?php

declare(strict_types=1);

namespace Shared\Condition;

use InvalidArgumentException;

final class InvalidCondition extends InvalidArgumentException
{
    public static function forNonNumericValue(Operator $operator, mixed $value): self
    {
        return new self(\sprintf('Condition "%s" needs a numeric "value", %s given.', $operator->value, CompareCondition::formatValue($value)));
    }
}
