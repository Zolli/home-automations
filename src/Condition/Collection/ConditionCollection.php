<?php

declare(strict_types=1);

namespace Shared\Condition\Collection;

use Shared\Condition\Condition;
use Stewart\Contracts\Collection\ListCollection;

/** @extends ListCollection<Condition> */
final readonly class ConditionCollection extends ListCollection
{
    /** @param iterable<Condition> $conditions */
    public static function fromConditions(iterable $conditions): self
    {
        return self::fromList($conditions);
    }
}
