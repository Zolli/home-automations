<?php

declare(strict_types=1);

namespace Shared\Alert\Escalation;

use Shared\Alert\Escalation\Collection\EscalationStepCollection;

final readonly class EscalationPolicy
{
    /** @throws InvalidEscalationPolicy */
    public function __construct(
        public string $name,
        public EscalationStepCollection $steps,
        public bool $acknowledgeOnDismiss = false,
    ) {
        if ($steps->isEmpty()) {
            throw InvalidEscalationPolicy::forMissingSteps();
        }

        if (!$steps->isOrderedByDelay()) {
            throw InvalidEscalationPolicy::forUnorderedSteps();
        }
    }
}
