<?php

declare(strict_types=1);

namespace Shared\Alert\Signal\Collection;

use Shared\Alert\Config\InvalidAlertConfig;
use Shared\Alert\Signal\SignalType;
use Stewart\Contracts\Collection\KeyedCollection;

/** @extends KeyedCollection<string, SignalType> */
final readonly class SignalTypeCollection extends KeyedCollection
{
    /**
     * @param iterable<SignalType> $types
     * @throws InvalidAlertConfig
     */
    public static function keyedByName(iterable $types): self
    {
        $typesByName = [];

        foreach ($types as $type) {
            $name = $type->getName();

            if (\array_key_exists($name, $typesByName)) {
                throw InvalidAlertConfig::forDuplicateType(SignalType::KIND, $name);
            }

            $typesByName[$name] = $type;
        }

        return self::fromElementsByKey($typesByName);
    }

    public function find(string $name): ?SignalType
    {
        return $this->elementAt($name);
    }

    /** @return list<string> */
    public function listNames(): array
    {
        return $this->mapToList(static fn(SignalType $type): string => $type->getName());
    }
}
