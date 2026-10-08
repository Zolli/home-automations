<?php

declare(strict_types=1);

namespace Shared\Alert\Escalation\Collection;

use Shared\Alert\Escalation\EscalationPolicy;
use Shared\Alert\Escalation\InvalidEscalationPolicy;
use Stewart\Contracts\Collection\KeyedCollection;

/** @extends KeyedCollection<string, EscalationPolicy> */
final readonly class EscalationPolicyCollection extends KeyedCollection
{
    /**
     * @param iterable<EscalationPolicy> $policies
     * @throws InvalidEscalationPolicy
     */
    public static function keyedByName(iterable $policies): self
    {
        $policiesByName = [];

        foreach ($policies as $policy) {
            if (\array_key_exists($policy->name, $policiesByName)) {
                throw InvalidEscalationPolicy::forDuplicateName($policy->name);
            }

            $policiesByName[$policy->name] = $policy;
        }

        return self::fromElementsByKey($policiesByName);
    }

    public function find(string $name): ?EscalationPolicy
    {
        return $this->elementAt($name);
    }
}
