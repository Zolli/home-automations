<?php

declare(strict_types=1);

namespace App\Tests\Shared\Condition\Collection;

use App\Tests\Shared\Condition\AlwaysConditionType;
use PHPUnit\Framework\TestCase;
use Shared\Condition\Collection\ConditionTypeCollection;
use Shared\Condition\Collection\InvalidConditionCollection;

final class ConditionTypeCollectionTest extends TestCase
{
    public function testFindsTypeByName(): void
    {
        $always = new AlwaysConditionType();
        $types = ConditionTypeCollection::keyedByName([new AlwaysConditionType('never'), $always]);

        self::assertSame($always, $types->find('always'));
        self::assertNull($types->find('sometimes'));
    }

    public function testListsNamesInRegistrationOrder(): void
    {
        $types = ConditionTypeCollection::keyedByName([new AlwaysConditionType('b'), new AlwaysConditionType('a')]);

        self::assertSame(['b', 'a'], $types->listNames());
    }

    public function testRejectsTwoTypesWithSameName(): void
    {
        $this->expectException(InvalidConditionCollection::class);

        ConditionTypeCollection::keyedByName([new AlwaysConditionType(), new AlwaysConditionType()]);
    }
}
