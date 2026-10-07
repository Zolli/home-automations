<?php

declare(strict_types=1);

namespace App\Tests\Shared\Condition;

use Shared\Condition\Condition;
use Shared\Condition\ConditionDefinition;
use Shared\Condition\ConditionType;

final readonly class AlwaysConditionType implements ConditionType
{
    public function __construct(private string $name = 'always') {}

    public function getName(): string
    {
        return $this->name;
    }

    public function mapToCondition(ConditionDefinition $definition): Condition
    {
        return new AlwaysCondition();
    }
}
