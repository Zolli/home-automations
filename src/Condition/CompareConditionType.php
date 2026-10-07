<?php

declare(strict_types=1);

namespace Shared\Condition;

final readonly class CompareConditionType implements ConditionType
{
    public function __construct(private Operator $operator) {}

    public function getName(): string
    {
        return $this->operator->value;
    }

    public function mapToCondition(ConditionDefinition $definition): Condition
    {
        return new CompareCondition(
            $definition->requireEntityId(),
            $this->operator,
            $definition->requireValue(),
            $definition->findAttribute(),
        );
    }
}
