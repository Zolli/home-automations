<?php

declare(strict_types=1);

namespace Shared\Alert\Collection;

use Shared\Alert\AlertDefinition;
use Shared\Alert\Config\InvalidAlertConfig;
use Stewart\Contracts\Collection\KeyedCollection;

/** @extends KeyedCollection<string, AlertDefinition> */
final readonly class AlertDefinitionCollection extends KeyedCollection
{
    /**
     * @param iterable<AlertDefinition> $definitions
     * @throws InvalidAlertConfig
     */
    public static function keyedByAlertId(iterable $definitions): self
    {
        $definitionsById = [];

        foreach ($definitions as $definition) {
            if (\array_key_exists($definition->id, $definitionsById)) {
                throw InvalidAlertConfig::forDuplicateAlert($definition->id);
            }

            $definitionsById[$definition->id] = $definition;
        }

        return self::fromElementsByKey($definitionsById);
    }

    public function find(string $id): ?AlertDefinition
    {
        return $this->elementAt($id);
    }
}
