<?php

declare(strict_types=1);

namespace App\Tests\Shared\Condition;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Condition\Collection\ConditionTypeCollection;
use Shared\Condition\CompareCondition;
use Shared\Condition\CompareConditionType;
use Shared\Condition\ConditionFactory;
use Shared\Condition\InvalidCondition;
use Shared\Condition\Operator;
use Stewart\Contracts\Entity\EntityId;

final class ConditionFactoryTest extends TestCase
{
    public function testBuildsRegisteredTypes(): void
    {
        $factory = new ConditionFactory(ConditionTypeCollection::keyedByName([new CompareConditionType(Operator::Gte), new AlwaysConditionType()]));

        self::assertInstanceOf(AlwaysCondition::class, $factory->mapToCondition(['type' => 'always']));
        self::assertEquals(
            new CompareCondition(new EntityId('sun.sun'), Operator::Gte, 10),
            $factory->mapToCondition(['type' => 'gte', 'field' => 'sun.sun', 'value' => 10]),
        );
    }

    /** @return iterable<string, array{array<array-key, mixed>}> */
    public static function invalidDefinitions(): iterable
    {
        yield 'missing type' => [['field' => 'sun.sun', 'value' => 1]];
        yield 'unknown type' => [['type' => 'between', 'field' => 'sun.sun', 'value' => 1]];
    }

    /** @param array<array-key, mixed> $definition */
    #[DataProvider('invalidDefinitions')]
    public function testRejectsInvalidDefinitions(array $definition): void
    {
        $this->expectException(InvalidCondition::class);

        new ConditionFactory(ConditionTypeCollection::keyedByName([new AlwaysConditionType()]))->mapToCondition($definition);
    }
}
