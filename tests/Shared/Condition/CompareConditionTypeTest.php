<?php

declare(strict_types=1);

namespace App\Tests\Shared\Condition;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Condition\CompareCondition;
use Shared\Condition\CompareConditionType;
use Shared\Condition\ConditionType;
use Shared\Condition\InvalidCondition;
use Shared\Condition\Operator;
use Shared\Config\InvalidTypedOptions;
use Shared\Config\TypedOptions;
use Stewart\Contracts\Entity\EntityId;
use Throwable;

final class CompareConditionTypeTest extends TestCase
{
    public function testIsNamedAfterOperator(): void
    {
        self::assertSame('lte', new CompareConditionType(Operator::Lte)->getName());
    }

    public function testBuildsCompareCondition(): void
    {
        $condition = new CompareConditionType(Operator::Gte)->mapToCondition(
            TypedOptions::fromArray(
                ConditionType::KIND,
                ['type' => 'gte', 'field' => 'sun.sun', 'attribute' => 'elevation', 'value' => 10],
            ),
        );

        self::assertEquals(new CompareCondition(new EntityId('sun.sun'), Operator::Gte, 10, 'elevation'), $condition);
    }

    /** @return iterable<string, array{Operator, array<array-key, mixed>, class-string<Throwable>}> */
    public static function invalidDefinitions(): iterable
    {
        yield 'missing field' => [Operator::Eq, ['type' => 'eq', 'value' => 'on'], InvalidTypedOptions::class];
        yield 'field without entity id' => [
            Operator::Eq,
            ['type' => 'eq', 'field' => 'guest_mode', 'value' => 'on'],
            InvalidTypedOptions::class,
        ];
        yield 'missing value' => [Operator::Eq, ['type' => 'eq', 'field' => 'sun.sun'], InvalidTypedOptions::class];
        yield 'non-string attribute' => [
            Operator::Eq,
            ['type' => 'eq', 'field' => 'sun.sun', 'value' => 1, 'attribute' => 5],
            InvalidTypedOptions::class,
        ];
        yield 'non-numeric ordering value' => [
            Operator::Gt,
            ['type' => 'gt', 'field' => 'sensor.temperature', 'value' => 'warm'],
            InvalidCondition::class,
        ];
    }

    /**
     * @param array<array-key, mixed> $definition
     * @param class-string<Throwable> $exception
     */
    #[DataProvider('invalidDefinitions')]
    public function testRejectsInvalidDefinitions(Operator $operator, array $definition, string $exception): void
    {
        $this->expectException($exception);

        new CompareConditionType($operator)->mapToCondition(TypedOptions::fromArray(ConditionType::KIND, $definition));
    }
}
