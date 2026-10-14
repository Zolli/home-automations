<?php

declare(strict_types=1);

namespace Shared\Condition\Collection;

use LogicException;

final class InvalidConditionCollection extends LogicException
{
    public static function forDuplicateFact(string $class): self
    {
        return new self("Fact \"{$class}\" is given more than once.");
    }
}
