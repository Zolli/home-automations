<?php

declare(strict_types=1);

namespace Shared\Alert\Rule;

use Shared\Alert\Config\InvalidAlertConfig;
use Shared\Alert\Config\TypedOptions;
use Shared\Alert\Rule\Collection\AlertRuleTypeCollection;

final readonly class AlertRuleFactory
{
    public function __construct(private AlertRuleTypeCollection $types) {}

    /**
     * @param array<array-key, mixed> $fields
     * @throws InvalidAlertConfig
     */
    public function mapToRule(array $fields): AlertRule
    {
        $options = TypedOptions::fromArray(AlertRuleType::KIND, $fields);
        $ruleType = $this->types->find($options->type)
            ?? throw InvalidAlertConfig::forUnknownType(AlertRuleType::KIND, $options->type, $this->types->listNames());

        return $ruleType->mapToRule($options);
    }
}
