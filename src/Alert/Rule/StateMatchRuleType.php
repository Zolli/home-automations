<?php

declare(strict_types=1);

namespace Shared\Alert\Rule;

use Shared\Alert\Config\ReadingSourceMapper;
use Shared\Config\TypedOptions;

final readonly class StateMatchRuleType implements AlertRuleType
{
    public function __construct(private ReadingSourceMapper $sources) {}

    public function getName(): string
    {
        return 'state';
    }

    public function mapToRule(TypedOptions $options): AlertRule
    {
        return new StateMatchRule(
            $this->sources->mapToSources($options),
            $options->findString('state') ?? 'on',
            $options->findDuration(self::HOLD_KEY),
        );
    }
}
