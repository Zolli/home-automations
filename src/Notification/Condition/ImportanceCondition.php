<?php

declare(strict_types=1);

namespace Shared\Notification\Condition;

use Shared\Condition\Condition;
use Shared\Condition\ConditionContext;
use Shared\Notification\Importance;
use Stewart\Contracts\Entity\EntityId;

final readonly class ImportanceCondition implements Condition
{
    public function __construct(public EntityId $entityId) {}

    public function isSatisfied(ConditionContext $context): bool
    {
        $importance = $context->facts->find(Importance::class) ?? Importance::DEFAULT;
        $state = $context->ha->getState($this->entityId)?->state;
        $threshold = $state === null ? null : Importance::tryFrom(strtolower($state));

        return $threshold === null || $importance->isAtLeast($threshold);
    }

    public function describe(): string
    {
        return "importance >= {$this->entityId->value}";
    }
}
