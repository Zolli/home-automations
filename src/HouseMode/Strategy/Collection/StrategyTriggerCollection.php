<?php

declare(strict_types=1);

namespace Shared\HouseMode\Strategy\Collection;

use Closure;
use Shared\HouseMode\Strategy\StrategyTrigger;
use Stewart\Contracts\Collection\KeyedCollection;

/** @extends KeyedCollection<string, StrategyTrigger> */
final readonly class StrategyTriggerCollection extends KeyedCollection
{
    /** @param iterable<StrategyTrigger> $triggers */
    public static function keyedByStrategyName(iterable $triggers): self
    {
        return self::keyedBy($triggers, static fn(StrategyTrigger $trigger): string => $trigger->getStrategyName());
    }

    public function find(string $strategyName): ?StrategyTrigger
    {
        return $this->elementAt($strategyName);
    }

    /** @param Closure(string): void $whenFired */
    public function armAll(Closure $whenFired): void
    {
        foreach ($this as $trigger) {
            $trigger->arm($whenFired);
        }
    }

    public function detachAll(): void
    {
        foreach ($this as $trigger) {
            $trigger->detach();
        }
    }
}
