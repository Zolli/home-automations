<?php

declare(strict_types=1);

namespace Shared\Condition\Collection;

use Stewart\Contracts\Collection\KeyedCollection;

/** @extends KeyedCollection<class-string, object> */
final readonly class FactCollection extends KeyedCollection
{
    /**
     * @param iterable<object> $facts
     * @throws InvalidConditionCollection
     */
    public static function keyedByClass(iterable $facts): self
    {
        $factsByClass = [];

        foreach ($facts as $fact) {
            if (\array_key_exists($fact::class, $factsByClass)) {
                throw InvalidConditionCollection::forDuplicateFact($fact::class);
            }

            $factsByClass[$fact::class] = $fact;
        }

        return self::fromElementsByKey($factsByClass);
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return T|null
     */
    public function find(string $type): ?object
    {
        $fact = $this->elementAt($type);

        return $fact instanceof $type ? $fact : null;
    }
}
