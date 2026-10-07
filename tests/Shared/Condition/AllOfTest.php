<?php

declare(strict_types=1);

namespace App\Tests\Shared\Condition;

use PHPUnit\Framework\TestCase;
use Shared\Condition\AllOf;
use Shared\Condition\CompareCondition;

final class AllOfTest extends TestCase
{
    public function testFlattensNestedAllOf(): void
    {
        $a = CompareCondition::equals('input_boolean.a', 'on');
        $b = CompareCondition::equals('input_boolean.b', 'on');

        self::assertEquals(new AllOf($a, $b), new AllOf(new AllOf(), new AllOf($a), $b));
    }

    public function testEmptyAllOfIsDescribedAsAlways(): void
    {
        self::assertSame('always', new AllOf()->describe());
    }
}
