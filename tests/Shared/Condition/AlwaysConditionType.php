<?php

declare(strict_types=1);

namespace App\Tests\Shared\Condition;

use Shared\Condition\Condition;
use Shared\Condition\ConditionType;
use Shared\Config\TypedOptions;

final readonly class AlwaysConditionType implements ConditionType
{
    public function __construct(
        private string $name = 'always',
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function mapToCondition(TypedOptions $options): Condition
    {
        return new AlwaysCondition();
    }
}
