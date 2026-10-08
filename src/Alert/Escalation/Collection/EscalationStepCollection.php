<?php

declare(strict_types=1);

namespace Shared\Alert\Escalation\Collection;

use Shared\Alert\Escalation\EscalationStep;
use Stewart\Contracts\Collection\ListCollection;

/** @extends ListCollection<EscalationStep> */
final readonly class EscalationStepCollection extends ListCollection
{
    /** @param iterable<EscalationStep> $steps */
    public static function fromSteps(iterable $steps): self
    {
        return self::fromList($steps);
    }

    public function isOrderedByDelay(): bool
    {
        $previous = null;

        foreach ($this as $step) {
            if ($previous !== null && $previous->after->isLongerThan($step->after)) {
                return false;
            }

            $previous = $step;
        }

        return true;
    }
}
