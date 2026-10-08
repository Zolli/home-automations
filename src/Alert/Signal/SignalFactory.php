<?php

declare(strict_types=1);

namespace Shared\Alert\Signal;

use Shared\Alert\Config\InvalidAlertConfig;
use Shared\Alert\Config\TypedOptions;
use Shared\Alert\Signal\Collection\SignalTypeCollection;

final readonly class SignalFactory
{
    public function __construct(private SignalTypeCollection $types) {}

    /**
     * @param array<array-key, mixed> $fields
     * @throws InvalidAlertConfig
     */
    public function mapToSignal(array $fields): Signal
    {
        $options = TypedOptions::fromArray(SignalType::KIND, $fields);
        $signalType = $this->types->find($options->type)
            ?? throw InvalidAlertConfig::forUnknownType(SignalType::KIND, $options->type, $this->types->listNames());

        return $signalType->mapToSignal($options);
    }
}
