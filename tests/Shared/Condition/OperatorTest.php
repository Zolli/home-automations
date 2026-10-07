<?php

declare(strict_types=1);

namespace App\Tests\Shared\Condition;

use JsonException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Condition\Operator;

final class OperatorTest extends TestCase
{
    /** @return iterable<string, array{Operator, mixed, mixed, bool}> */
    public static function comparisons(): iterable
    {
        yield 'eq strings' => [Operator::Eq, 'on', 'on', true];
        yield 'eq different strings' => [Operator::Eq, 'on', 'off', false];
        yield 'eq numeric state and number' => [Operator::Eq, '21.0', 21, true];
        yield 'eq bool and string' => [Operator::Eq, true, 'true', true];
        yield 'eq null' => [Operator::Eq, null, 'on', false];
        yield 'neq strings' => [Operator::Neq, 'on', 'off', true];
        yield 'neq same number' => [Operator::Neq, '5', 5.0, false];
        yield 'gt numeric state' => [Operator::Gt, '21.5', 21, true];
        yield 'gt equal' => [Operator::Gt, 21, 21, false];
        yield 'gte equal' => [Operator::Gte, 21, '21', true];
        yield 'lt' => [Operator::Lt, -3, 10, true];
        yield 'lte bigger' => [Operator::Lte, 11, 10, false];
        yield 'gt non-numeric' => [Operator::Gt, 'unavailable', 10, false];
        yield 'lt non-numeric' => [Operator::Lt, 'b', 'c', false];
    }

    #[DataProvider('comparisons')]
    public function testCompare(Operator $operator, mixed $actual, mixed $expected, bool $result): void
    {
        self::assertSame($result, $operator->compare($actual, $expected));
    }

    public function testRejectsValuesThatCannotBeEncoded(): void
    {
        $this->expectException(JsonException::class);

        Operator::Eq->compare([\NAN], [\NAN]);
    }
}
