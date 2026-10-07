<?php

declare(strict_types=1);

namespace Shared\Condition;

use Shared\Condition\Collection\FactCollection;
use Stewart\Contracts\HaContext;

final readonly class ConditionContext
{
    public FactCollection $facts;

    public function __construct(
        public HaContext $ha,
        ?FactCollection $facts = null,
    ) {
        $this->facts = $facts ?? FactCollection::empty();
    }
}
