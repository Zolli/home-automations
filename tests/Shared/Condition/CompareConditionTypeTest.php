<?php

declare(strict_types=1);

namespace App\Tests\Shared\Condition;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Condition\CompareCondition;
use Shared\Condition\CompareConditionType;
use Shared\Condition\ConditionDefinition;
use Shared\Condition\InvalidCondition;
use Shared\Condition\Operator;
use Stewart\Contracts\Entity\EntityId;

final class CompareConditionTypeTest extends TestCase
{
    public function testIsNamedAfterOperator(): void
    {
        self::assertSame('lte', new CompareConditionType(Operator::Lte)->getName());
    }

    public function testBuildsCompareCondition(): void
    {
        $condition = new CompareConditionType(Operator::Gte)->mapToCondition(
            ConditionDefinition::fromArray(['type' => 'gte', 'field' => 'sun.sun', 'attribute' => 'elevation', 'value' => 10]),
        );

        self::assertEquals(new CompareCondition(new EntityId('sun.sun'), Operator::Gte, 10, 'elevation'), $condition);
    }

    /** @return iterable<string, array{Operator, array<array-key, mixed>}> */
    public static function invalidDefinitions(): iterable
    {
        yield 'missing field' => [Operator::Eq, ['type' => 'eq', 'value' => 'on']];
        yield 'field without entity id' => [Operator::Eq, ['type' => 'eq', 'field' => 'guest_mode', 'value' => 'on']];
        yield 'missing value' => [Operator::Eq, ['type' => 'eq', 'field' => 'sun.sun']];
        yield 'non-string attribute' => [Operator::Eq, ['type' => 'eq', 'field' => 'sun.sun', 'value' => 1, 'attribute' => 5]];
        yield 'non-numeric ordering value' => [Operator::Gt, ['type' => 'gt', 'field' => 'sensor.temperature', 'value' => 'warm']];
    }

    /** @param array<array-key, mixed> $definition */
    #[DataProvider('invalidDefinitions')]
    public function testRejectsInvalidDefinitions(Operator $operator, array $definition): void
    {
        $this->expectException(InvalidCondition::class);

        new CompareConditionType($operator)->mapToCondition(ConditionDefinition::fromArray($definition));
    }
}
