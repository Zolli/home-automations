<?php

declare(strict_types=1);

namespace Shared\Alert\Escalation;

use Shared\Condition\AllOf;
use Shared\Condition\Condition;
use Stewart\Contracts\Time\Duration;

final readonly class StepRepetition
{
    /** @throws InvalidEscalationPolicy */
    public function __construct(
        public Duration $every,
        public ?int $maxTimes = null,
        public Condition $while = new AllOf(),
    ) {
        if (!$every->isLongerThan(Duration::zero())) {
            throw InvalidEscalationPolicy::forZeroRepeatInterval();
        }

        if ($maxTimes !== null && $maxTimes < 1) {
            throw InvalidEscalationPolicy::forNonPositiveRepeatTimes($maxTimes);
        }
    }

    public function allowsAnotherRepeat(int $repeatsSent): bool
    {
        return $this->maxTimes === null || $repeatsSent < $this->maxTimes;
    }
}
