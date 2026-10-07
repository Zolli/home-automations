<?php

declare(strict_types=1);

namespace App\Tests\Shared\Condition;

use PHPUnit\Framework\TestCase;
use Shared\Condition\Collection\FactCollection;
use Shared\Condition\ConditionContext;
use Stewart\Contracts\Entity\EntityId;
use Stewart\Contracts\HaContext;
use Stewart\Contracts\State\EntityState;

final class StateStub
{
    /**
     * @param array<string, array{0: string, 1?: array<string, mixed>}> $states
     * @param list<object> $facts
     */
    public static function context(TestCase $test, array $states, array $facts = []): ConditionContext
    {
        $ha = (fn() => $this->createStub(HaContext::class))->call($test);
        $ha->method('getState')->willReturnCallback(
            static fn(EntityId|string $id): ?EntityState => isset($states[(string) $id])
                ? new EntityState(new EntityId((string) $id), $states[(string) $id][0], $states[(string) $id][1] ?? [])
                : null,
        );

        return new ConditionContext($ha, FactCollection::keyedByClass($facts));
    }
}
