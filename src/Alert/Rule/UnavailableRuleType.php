<?php

declare(strict_types=1);

namespace Shared\Alert\Rule;

use Shared\Alert\Config\ReadingSourceMapper;
use Shared\Config\TypedOptions;

final readonly class UnavailableRuleType implements AlertRuleType
{
    public function __construct(
        private ReadingSourceMapper $sources,
    ) {}

    public function getName(): string
    {
        return 'unavailable';
    }

    public function mapToRule(TypedOptions $options): AlertRule
    {
        return new UnavailableRule($this->sources->mapToSources($options), $options->findDuration(self::HOLD_KEY));
    }
}
