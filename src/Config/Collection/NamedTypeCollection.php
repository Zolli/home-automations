<?php

declare(strict_types=1);

namespace Shared\Config\Collection;

use Shared\Config\InvalidTypedOptions;
use Shared\Config\NamedType;
use Shared\Config\TypedOptions;
use Stewart\Contracts\Collection\KeyedCollection;

/**
 * @template T of NamedType
 * @extends KeyedCollection<string, T>
 */
abstract readonly class NamedTypeCollection extends KeyedCollection
{
    /**
     * @param iterable<T> $types
     * @throws InvalidNamedTypeCollection
     */
    public static function keyedByName(iterable $types): static
    {
        $typesByName = [];

        foreach ($types as $type) {
            $name = $type->getName();

            if (\array_key_exists($name, $typesByName)) {
                throw InvalidNamedTypeCollection::forDuplicateName($type);
            }

            $typesByName[$name] = $type;
        }

        return static::fromElementsByKey($typesByName);
    }

    /** @return T|null */
    public function find(string $name): ?NamedType
    {
        return $this->elementAt($name);
    }

    /**
     * @return T
     * @throws InvalidTypedOptions
     */
    public function requireType(TypedOptions $options): NamedType
    {
        return $this->find($options->type) ?? throw InvalidTypedOptions::forUnknownType($options->kind, $options->type, $this->listNames());
    }

    /** @return list<string> */
    private function listNames(): array
    {
        return $this->mapToList(static fn(NamedType $type): string => $type->getName());
    }
}
