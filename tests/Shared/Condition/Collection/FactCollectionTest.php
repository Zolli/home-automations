<?php

declare(strict_types=1);

namespace App\Tests\Shared\Condition\Collection;

use PHPUnit\Framework\TestCase;
use Shared\Condition\Collection\FactCollection;
use Shared\Condition\Collection\InvalidConditionCollection;
use Shared\Notification\Importance;
use Stewart\Contracts\Time\Duration;

final class FactCollectionTest extends TestCase
{
    public function testFindsFactByClass(): void
    {
        $facts = FactCollection::keyedByClass([Duration::hours(1), Importance::High]);

        self::assertSame(Importance::High, $facts->find(Importance::class));
    }

    public function testMissingFactIsNull(): void
    {
        self::assertNull(FactCollection::empty()->find(Importance::class));
    }

    public function testRejectsDuplicateFactClass(): void
    {
        $this->expectException(InvalidConditionCollection::class);

        FactCollection::keyedByClass([Importance::High, Importance::Low]);
    }
}
