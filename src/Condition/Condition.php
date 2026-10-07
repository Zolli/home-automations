<?php

declare(strict_types=1);

namespace Shared\Condition;

interface Condition
{
    public function isSatisfied(ConditionContext $context): bool;

    public function describe(): string;
}
