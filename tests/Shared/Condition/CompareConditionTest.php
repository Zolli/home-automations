<?php

declare(strict_types=1);

namespace App\Tests\Shared\Condition;

use PHPUnit\Framework\TestCase;
use Shared\Condition\AllOf;
use Shared\Condition\CompareCondition;
use Shared\Condition\InvalidCondition;
use Shared\Condition\Operator;
use Stewart\Contracts\Entity\EntityId;
use Stewart\Contracts\Exception\IdentifierException;

final class CompareConditionTest extends TestCase
{
    public function testComparesState(): void
    {
        $context = StateStub::context($this, ['input_boolean.guest_mode' => ['off']]);

        self::assertTrue(CompareCondition::equals('input_boolean.guest_mode', 'off')->isSatisfied($context));
        self::assertFalse(CompareCondition::notEquals('input_boolean.guest_mode', 'off')->isSatisfied($context));
    }

    public function testComparesAttribute(): void
    {
        $context = StateStub::context($this, ['sun.sun' => ['above_horizon', ['elevation' => 4.2]]]);

        self::assertTrue(CompareCondition::lessThan('sun.sun', 10, 'elevation')->isSatisfied($context));
        self::assertFalse(CompareCondition::atLeast('sun.sun', 10, 'elevation')->isSatisfied($context));
    }

    public function testMissingEntityIsNeverSatisfied(): void
    {
        $context = StateStub::context($this, []);

        self::assertFalse(CompareCondition::notEquals('sensor.missing', 'on')->isSatisfied($context));
    }

    public function testRejectsFieldThatIsNotEntityId(): void
    {
        $this->expectException(IdentifierException::class);

        CompareCondition::equals('guest_mode', 'on');
    }

    public function testRejectsNonNumericValueForOrdering(): void
    {
        $this->expectException(InvalidCondition::class);

        new CompareCondition(new EntityId('sensor.temperature'), Operator::Lt, 'cold');
    }

    public function testAcceptsNumericStringForOrdering(): void
    {
        $context = StateStub::context($this, ['sensor.temperature' => ['18.5']]);

        self::assertTrue(new CompareCondition(new EntityId('sensor.temperature'), Operator::Lt, '20')->isSatisfied($context));
    }

    public function testAllOfNeedsEveryCondition(): void
    {
        $context = StateStub::context($this, ['input_boolean.guest_mode' => ['off'], 'sensor.temperature' => ['18.5']]);

        self::assertTrue(new AllOf()->isSatisfied($context));
        self::assertTrue(new AllOf(
            CompareCondition::equals('input_boolean.guest_mode', 'off'),
            CompareCondition::lessThan('sensor.temperature', 20),
        )->isSatisfied($context));
        self::assertFalse(new AllOf(
            CompareCondition::equals('input_boolean.guest_mode', 'off'),
            CompareCondition::greaterThan('sensor.temperature', 20),
        )->isSatisfied($context));
    }

    public function testDescribe(): void
    {
        self::assertSame(
            'input_boolean.guest_mode eq "off" and sun.sun[elevation] lt 10',
            new AllOf(CompareCondition::equals('input_boolean.guest_mode', 'off'), CompareCondition::lessThan('sun.sun', 10, 'elevation'))->describe(),
        );
    }

    public function testDescribesUnencodableValueAsNull(): void
    {
        self::assertSame('sensor.name eq null', CompareCondition::equals('sensor.name', "\xB1")->describe());
    }
}
