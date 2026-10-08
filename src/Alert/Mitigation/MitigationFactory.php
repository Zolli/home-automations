<?php

declare(strict_types=1);

namespace Shared\Alert\Mitigation;

use Shared\Alert\Config\InvalidAlertConfig;
use Shared\Alert\Config\TypedOptions;
use Shared\Alert\Mitigation\Collection\MitigationCollection;
use Shared\Alert\Mitigation\Collection\MitigationTypeCollection;

final readonly class MitigationFactory
{
    public function __construct(private MitigationTypeCollection $types) {}

    /**
     * @param array<array-key, mixed> $mitigations
     * @throws InvalidAlertConfig
     */
    public function mapToMitigations(array $mitigations): MitigationCollection
    {
        $mapped = [];

        foreach ($mitigations as $fields) {
            $mapped[] = $this->mapToMitigation(\is_array($fields) ? $fields : []);
        }

        return MitigationCollection::fromMitigations($mapped);
    }

    /**
     * @param array<array-key, mixed> $fields
     * @throws InvalidAlertConfig
     */
    private function mapToMitigation(array $fields): Mitigation
    {
        $options = TypedOptions::fromArray(MitigationType::KIND, $fields);
        $mitigationType = $this->types->find($options->type)
            ?? throw InvalidAlertConfig::forUnknownType(MitigationType::KIND, $options->type, $this->types->listNames());

        return $mitigationType->mapToMitigation($options);
    }
}
