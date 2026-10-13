<?php

declare(strict_types=1);

namespace App\Tests\Shared\Condition;

use Shared\Condition\Collection\FactCollection;
use Shared\Condition\ConditionContext;
use Stewart\Testing\HaContext\RecordingHaContext;

final class ConditionContextFixture
{
    /**
     * @param array<string, array{0: string, 1?: array<string, mixed>}> $states
     * @param list<object> $facts
     */
    public static function createWithStates(array $states, array $facts = []): ConditionContext
    {
        $ha = new RecordingHaContext();

        foreach ($states as $entityId => $state) {
            $ha->seedState($entityId, $state[0], $state[1] ?? []);
        }

        return new ConditionContext($ha, FactCollection::keyedByClass($facts));
    }
}
