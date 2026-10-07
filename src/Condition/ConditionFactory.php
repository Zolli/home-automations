<?php

declare(strict_types=1);

namespace Shared\Condition;

use Shared\Condition\Collection\ConditionTypeCollection;

final readonly class ConditionFactory
{
    public function __construct(private ConditionTypeCollection $types) {}

    /**
     * @param array<array-key, mixed> $fields
     * @throws InvalidCondition
     */
    public function mapToCondition(array $fields): Condition
    {
        $definition = ConditionDefinition::fromArray($fields);
        $conditionType = $this->types->find($definition->type)
            ?? throw InvalidCondition::forUnknownType($definition->type, $this->types->listNames());

        return $conditionType->mapToCondition($definition);
    }
}
