<?php

declare(strict_types=1);

namespace Shared\Notification\Condition;

use Shared\Condition\Condition;
use Shared\Condition\ConditionDefinition;
use Shared\Condition\ConditionType;

final readonly class ImportanceConditionType implements ConditionType
{
    public function getName(): string
    {
        return 'importance';
    }

    public function mapToCondition(ConditionDefinition $definition): Condition
    {
        return new ImportanceCondition($definition->requireEntityId());
    }
}
