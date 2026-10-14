<?php

declare(strict_types=1);

namespace Shared\Alert\Rule\Collection;

use Shared\Alert\Rule\AlertRuleType;
use Shared\Config\Collection\NamedTypeCollection;

/** @extends NamedTypeCollection<AlertRuleType> */
final readonly class AlertRuleTypeCollection extends NamedTypeCollection {}
