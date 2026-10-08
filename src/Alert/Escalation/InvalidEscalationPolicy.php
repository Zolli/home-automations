<?php

declare(strict_types=1);

namespace Shared\Alert\Escalation;

use InvalidArgumentException;

final class InvalidEscalationPolicy extends InvalidArgumentException
{
    public static function forMissingSteps(): self
    {
        return new self('Escalation policy needs at least one step.');
    }

    public static function forUnorderedSteps(): self
    {
        return new self('Escalation policy needs its steps ordered by delay.');
    }

    public static function forZeroRepeatInterval(): self
    {
        return new self('Escalation step needs a repeat interval above zero.');
    }

    public static function forNonPositiveRepeatTimes(int $times): self
    {
        return new self("Escalation step needs a positive repeat count, {$times} given.");
    }

    public static function forStepWithoutSingleAction(): self
    {
        return new self('Escalation step needs either destinations or a signal.');
    }

    public static function forDuplicateName(string $name): self
    {
        return new self("Escalation policy \"{$name}\" is defined twice.");
    }
}
