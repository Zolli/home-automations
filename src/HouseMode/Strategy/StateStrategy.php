<?php

declare(strict_types=1);

namespace Shared\HouseMode\Strategy;

use Shared\HouseMode\TransitionContext;
use Stewart\Contracts\Entity\EntityId;
use Stewart\Contracts\Time\Duration;

final readonly class StateStrategy implements ModeStrategy
{
    public function __construct(
        public string $name,
        public EntityId $entityId,
        public string $state,
        public ?Duration $holdDuration = null,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function attach(TransitionContext $context): StrategyTrigger
    {
        return new StateTrigger($this, $context->mode->ha);
    }
}
