<?php

declare(strict_types=1);

namespace Shared\Alert\Signal;

use Shared\Alert\Signal\Collection\SignalTypeCollection;
use Shared\Config\InvalidTypedOptions;
use Shared\Config\TypedOptions;

final readonly class SignalFactory
{
    public function __construct(
        private SignalTypeCollection $types,
    ) {}

    /**
     * @param array<array-key, mixed> $fields
     * @throws InvalidTypedOptions
     */
    public function mapToSignal(array $fields): Signal
    {
        $options = TypedOptions::fromArray(SignalType::KIND, $fields);

        return $this->types->requireType($options)->mapToSignal($options);
    }
}
