<?php

declare(strict_types=1);

namespace Shared\Alert\Rule;

use Shared\Alert\Rule\Collection\AlertRuleTypeCollection;
use Shared\Config\InvalidTypedOptions;
use Shared\Config\TypedOptions;

final readonly class AlertRuleFactory
{
    public function __construct(private AlertRuleTypeCollection $types) {}

    /**
     * @param array<array-key, mixed> $fields
     * @throws InvalidTypedOptions
     */
    public function mapToRule(array $fields): AlertRule
    {
        $options = TypedOptions::fromArray(AlertRuleType::KIND, $fields);

        return $this->types->requireType($options)->mapToRule($options);
    }
}
