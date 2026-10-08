<?php

declare(strict_types=1);

namespace Shared\Alert\Rule;

use Shared\Alert\Config\InvalidAlertConfig;
use Shared\Alert\Config\TypedOptions;
use Stewart\Contracts\Time\Duration;

final readonly class SilenceRuleType implements AlertRuleType
{
    private const string MAX_SILENCE_KEY = 'maxSilenceSeconds';

    public function getName(): string
    {
        return 'silent';
    }

    public function mapToRule(TypedOptions $options): AlertRule
    {
        $maxSilenceSeconds = $options->requireNumber(self::MAX_SILENCE_KEY);

        if ($maxSilenceSeconds <= 0) {
            throw InvalidAlertConfig::forInvalidOption($options->kind, $options->type, self::MAX_SILENCE_KEY, 'a positive number');
        }

        return new SilenceRule($options->requireTopicSources(), Duration::seconds($maxSilenceSeconds), $options->findHoldDuration());
    }
}
