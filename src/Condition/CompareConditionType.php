<?php

declare(strict_types=1);

namespace Shared\Condition;

use Shared\Config\TypedOptions;

final readonly class CompareConditionType implements ConditionType
{
    public function __construct(
        private Operator $operator,
    ) {}

    public function getName(): string
    {
        return $this->operator->value;
    }

    public function mapToCondition(TypedOptions $options): Condition
    {
        return new CompareCondition(
            $options->requireEntityId('field'),
            $this->operator,
            $options->requireValue('value'),
            $options->findString('attribute'),
        );
    }
}
