<?php

declare(strict_types=1);

namespace Shared\HouseMode\Collection;

use Closure;
use Shared\HouseMode\InvalidModeConfig;
use Shared\HouseMode\TransitionContext;
use Shared\HouseMode\Trigger\ModeTrigger;
use Stewart\Contracts\Collection\KeyedCollection;
use Stewart\Contracts\Exception\ExposureException;

/** @extends KeyedCollection<string, ModeTrigger> */
final readonly class ModeTriggerCollection extends KeyedCollection
{
    /**
     * @param iterable<ModeTrigger> $triggers
     * @throws InvalidModeConfig
     */
    public static function keyedByStrategyName(iterable $triggers): self
    {
        $triggersByName = [];

        foreach ($triggers as $trigger) {
            if (\array_key_exists($trigger->getName(), $triggersByName)) {
                throw InvalidModeConfig::forDuplicateStrategy($trigger->getName());
            }

            $triggersByName[$trigger->getName()] = $trigger;
        }

        return self::fromElementsByKey($triggersByName);
    }

    public function find(string $strategyName): ?ModeTrigger
    {
        return $this->elementAt($strategyName);
    }

    /** @return list<string> */
    public function listNames(): array
    {
        return $this->mapToList(static fn(ModeTrigger $trigger): string => $trigger->getName());
    }

    /** @throws ExposureException */
    public function attachAll(TransitionContext $context): void
    {
        foreach ($this as $trigger) {
            $trigger->attach($context);
        }
    }

    /** @param Closure(string): void $whenFired */
    public function armAll(TransitionContext $context, Closure $whenFired): void
    {
        foreach ($this as $trigger) {
            $trigger->arm($context, $whenFired);
        }
    }

    public function detachAll(): void
    {
        foreach ($this as $trigger) {
            $trigger->detach();
        }
    }
}
