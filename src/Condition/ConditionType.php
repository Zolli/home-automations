<?php

declare(strict_types=1);

namespace Shared\Condition;

interface ConditionType
{
    public function getName(): string;

    /** @throws InvalidCondition */
    public function mapToCondition(ConditionDefinition $definition): Condition;
}
