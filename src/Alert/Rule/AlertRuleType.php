<?php

declare(strict_types=1);

namespace Shared\Alert\Rule;

use Shared\Alert\Config\InvalidAlertConfig;
use Shared\Alert\Config\TypedOptions;

interface AlertRuleType
{
    public const string KIND = 'Alert rule';

    public function getName(): string;

    /** @throws InvalidAlertConfig */
    public function mapToRule(TypedOptions $options): AlertRule;
}
