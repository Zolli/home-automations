<?php

declare(strict_types=1);

namespace Shared\HouseMode\Strategy;

use Shared\HouseMode\TransitionContext;
use Shared\Threshold\ThresholdDirection;
use Stewart\Contracts\Entity\EntityId;
use Stewart\Contracts\Time\Duration;

final readonly class ThresholdStrategy implements ModeStrategy
{
    public function __construct(
        public string $name,
        public EntityId $entityId,
        public ThresholdDirection $direction,
        public float $initialThreshold,
        public ThresholdRange $range,
        public float $hysteresis = 0.0,
        public ?Duration $holdDuration = null,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function attach(TransitionContext $context): StrategyTrigger
    {
        $trigger = new ThresholdTrigger(
            $this,
            $context->exposeThreshold($this->name, $this->range, $this->initialThreshold),
            $context->mode->ha,
        );
        $trigger->watchThresholdCommands();

        return $trigger;
    }
}
