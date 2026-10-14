<?php

declare(strict_types=1);

namespace Shared\Alert\Rule;

use Shared\Alert\Config\ReadingSourceMapper;
use Shared\Config\InvalidTypedOptions;
use Shared\Config\TypedOptions;
use Stewart\Contracts\Time\Duration;

final readonly class SilenceRuleType implements AlertRuleType
{
    private const string MAX_SILENCE_KEY = 'maxSilenceSeconds';

    public function __construct(private ReadingSourceMapper $sources) {}

    public function getName(): string
    {
        return 'silent';
    }

    public function mapToRule(TypedOptions $options): AlertRule
    {
        $maxSilenceSeconds = $options->requireNumber(self::MAX_SILENCE_KEY);

        if ($maxSilenceSeconds <= 0) {
            throw InvalidTypedOptions::forInvalidOption($options->kind, $options->type, self::MAX_SILENCE_KEY, 'a positive number');
        }

        return new SilenceRule($this->sources->mapToTopicSources($options), Duration::seconds($maxSilenceSeconds), $options->findDuration(self::HOLD_KEY));
    }
}
