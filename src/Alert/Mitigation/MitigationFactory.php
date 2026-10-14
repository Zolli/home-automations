<?php

declare(strict_types=1);

namespace Shared\Alert\Mitigation;

use Shared\Alert\Mitigation\Collection\MitigationCollection;
use Shared\Alert\Mitigation\Collection\MitigationTypeCollection;
use Shared\Config\InvalidTypedOptions;
use Shared\Config\TypedOptions;

final readonly class MitigationFactory
{
    public function __construct(private MitigationTypeCollection $types) {}

    /**
     * @param array<array-key, mixed> $mitigations
     * @throws InvalidTypedOptions
     */
    public function mapToMitigations(array $mitigations): MitigationCollection
    {
        $mapped = [];

        foreach ($mitigations as $fields) {
            $options = TypedOptions::fromArray(MitigationType::KIND, \is_array($fields) ? $fields : []);
            $mapped[] = $this->types->requireType($options)->mapToMitigation($options);
        }

        return MitigationCollection::fromMitigations($mapped);
    }
}
