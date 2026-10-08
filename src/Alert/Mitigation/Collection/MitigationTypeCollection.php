<?php

declare(strict_types=1);

namespace Shared\Alert\Mitigation\Collection;

use Shared\Alert\Config\InvalidAlertConfig;
use Shared\Alert\Mitigation\MitigationType;
use Stewart\Contracts\Collection\KeyedCollection;

/** @extends KeyedCollection<string, MitigationType> */
final readonly class MitigationTypeCollection extends KeyedCollection
{
    /**
     * @param iterable<MitigationType> $types
     * @throws InvalidAlertConfig
     */
    public static function keyedByName(iterable $types): self
    {
        $typesByName = [];

        foreach ($types as $type) {
            $name = $type->getName();

            if (\array_key_exists($name, $typesByName)) {
                throw InvalidAlertConfig::forDuplicateType(MitigationType::KIND, $name);
            }

            $typesByName[$name] = $type;
        }

        return self::fromElementsByKey($typesByName);
    }

    public function find(string $name): ?MitigationType
    {
        return $this->elementAt($name);
    }

    /** @return list<string> */
    public function listNames(): array
    {
        return $this->mapToList(static fn(MitigationType $type): string => $type->getName());
    }
}
