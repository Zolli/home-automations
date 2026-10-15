<?php

declare(strict_types=1);

namespace Shared\Condition;

use Shared\Condition\Collection\ConditionTypeCollection;
use Shared\Config\InvalidTypedOptions;
use Shared\Config\TypedOptions;

final readonly class ConditionFactory
{
    public function __construct(
        private ConditionTypeCollection $types,
    ) {}

    /**
     * @param array<array-key, mixed> $fields
     * @throws InvalidTypedOptions
     * @throws InvalidCondition
     */
    public function mapToCondition(array $fields): Condition
    {
        $options = TypedOptions::fromArray(ConditionType::KIND, $fields);

        return $this->types->requireType($options)->mapToCondition($options);
    }
}
