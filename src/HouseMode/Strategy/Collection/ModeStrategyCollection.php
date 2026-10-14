<?php

declare(strict_types=1);

namespace Shared\HouseMode\Strategy\Collection;

use Shared\HouseMode\Strategy\ModeStrategy;
use Shared\HouseMode\TransitionContext;
use Stewart\Contracts\Collection\KeyedCollection;
use Stewart\Contracts\Exception\ExposureException;

/** @extends KeyedCollection<string, ModeStrategy> */
final readonly class ModeStrategyCollection extends KeyedCollection
{
    /**
     * @param iterable<ModeStrategy> $strategies
     * @throws InvalidModeStrategyCollection
     */
    public static function keyedByStrategyName(iterable $strategies): self
    {
        $strategiesByName = [];

        foreach ($strategies as $strategy) {
            if (\array_key_exists($strategy->getName(), $strategiesByName)) {
                throw InvalidModeStrategyCollection::forDuplicateName($strategy->getName());
            }

            $strategiesByName[$strategy->getName()] = $strategy;
        }

        return self::fromElementsByKey($strategiesByName);
    }

    public function find(string $name): ?ModeStrategy
    {
        return $this->elementAt($name);
    }

    /** @return list<string> */
    public function listNames(): array
    {
        return $this->mapToList(static fn(ModeStrategy $strategy): string => $strategy->getName());
    }

    /** @throws ExposureException */
    public function attachAll(TransitionContext $context): StrategyTriggerCollection
    {
        return StrategyTriggerCollection::keyedByStrategyName(
            $this->mapToList(static fn(ModeStrategy $strategy) => $strategy->attach($context)),
        );
    }
}
