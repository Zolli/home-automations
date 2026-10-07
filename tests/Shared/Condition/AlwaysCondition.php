<?php

declare(strict_types=1);

namespace App\Tests\Shared\Condition;

use Shared\Condition\Condition;
use Shared\Condition\ConditionContext;

final readonly class AlwaysCondition implements Condition
{
    public function isSatisfied(ConditionContext $context): bool
    {
        return true;
    }

    public function describe(): string
    {
        return 'always';
    }
}
