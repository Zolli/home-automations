<?php

declare(strict_types=1);

namespace Shared\Condition;

use Shared\Condition\Collection\ConditionCollection;

final readonly class AllOf implements Condition
{
    public ConditionCollection $conditions;

    public function __construct(Condition ...$conditions)
    {
        $this->conditions = ConditionCollection::fromConditions($this->flattenConditions($conditions));
    }

    public function isSatisfied(ConditionContext $context): bool
    {
        foreach ($this->conditions as $condition) {
            if (!$condition->isSatisfied($context)) {
                return false;
            }
        }

        return true;
    }

    public function describe(): string
    {
        return $this->conditions->isEmpty()
            ? 'always'
            : implode(' and ', $this->conditions->mapToList(static fn(Condition $condition) => $condition->describe()));
    }

    /**
     * @param array<Condition> $conditions
     * @return iterable<Condition>
     */
    private function flattenConditions(array $conditions): iterable
    {
        foreach ($conditions as $condition) {
            yield from $condition instanceof self ? $condition->conditions : [$condition];
        }
    }
}
