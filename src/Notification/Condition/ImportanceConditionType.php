<?php

declare(strict_types=1);

namespace Shared\Notification\Condition;

use Shared\Condition\Condition;
use Shared\Condition\ConditionType;
use Shared\Config\TypedOptions;

final readonly class ImportanceConditionType implements ConditionType
{
    public function getName(): string
    {
        return 'importance';
    }

    public function mapToCondition(TypedOptions $options): Condition
    {
        return new ImportanceCondition($options->requireEntityId('field'));
    }
}
