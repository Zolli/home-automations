<?php

declare(strict_types=1);

namespace Shared\Condition\Collection;

use Shared\Condition\ConditionType;
use Stewart\Contracts\Collection\KeyedCollection;

/** @extends KeyedCollection<string, ConditionType> */
final readonly class ConditionTypeCollection extends KeyedCollection
{
    /**
     * @param iterable<ConditionType> $types
     * @throws InvalidConditionCollection
     */
    public static function keyedByName(iterable $types): self
    {
        $typesByName = [];

        foreach ($types as $type) {
            $name = $type->getName();

            if (\array_key_exists($name, $typesByName)) {
                throw InvalidConditionCollection::forDuplicateTypeName($name);
            }

            $typesByName[$name] = $type;
        }

        return self::fromElementsByKey($typesByName);
    }

    public function find(string $name): ?ConditionType
    {
        return $this->elementAt($name);
    }

    /** @return list<string> */
    public function listNames(): array
    {
        return $this->mapToList(static fn(ConditionType $type): string => $type->getName());
    }
}
