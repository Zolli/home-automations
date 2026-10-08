<?php

declare(strict_types=1);

namespace Shared\Alert\Rule\Collection;

use Shared\Alert\Config\InvalidAlertConfig;
use Shared\Alert\Rule\AlertRuleType;
use Stewart\Contracts\Collection\KeyedCollection;

/** @extends KeyedCollection<string, AlertRuleType> */
final readonly class AlertRuleTypeCollection extends KeyedCollection
{
    /**
     * @param iterable<AlertRuleType> $types
     * @throws InvalidAlertConfig
     */
    public static function keyedByName(iterable $types): self
    {
        $typesByName = [];

        foreach ($types as $type) {
            $name = $type->getName();

            if (\array_key_exists($name, $typesByName)) {
                throw InvalidAlertConfig::forDuplicateType(AlertRuleType::KIND, $name);
            }

            $typesByName[$name] = $type;
        }

        return self::fromElementsByKey($typesByName);
    }

    public function find(string $name): ?AlertRuleType
    {
        return $this->elementAt($name);
    }

    /** @return list<string> */
    public function listNames(): array
    {
        return $this->mapToList(static fn(AlertRuleType $type): string => $type->getName());
    }
}
