<?php

declare(strict_types=1);

namespace Shared\Alert\Escalation;

use Shared\Alert\Signal\Signal;
use Shared\Notification\Collection\DestinationCollection;
use Shared\Notification\Importance;
use Stewart\Contracts\Time\Duration;

final readonly class EscalationStep
{
    /** @throws InvalidEscalationPolicy */
    public function __construct(
        public Duration $after,
        public DestinationCollection $destinations,
        public Importance $importance = Importance::Critical,
        public ?StepRepetition $repetition = null,
        public ?Signal $signal = null,
    ) {
        if ($destinations->isEmpty() === ($signal === null)) {
            throw InvalidEscalationPolicy::forStepWithoutSingleAction();
        }
    }

    /** @throws InvalidEscalationPolicy */
    public static function forSignal(Duration $after, Signal $signal, ?StepRepetition $repetition = null): self
    {
        return new self($after, DestinationCollection::empty(), repetition: $repetition, signal: $signal);
    }
}
