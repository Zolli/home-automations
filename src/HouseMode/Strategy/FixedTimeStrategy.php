<?php

declare(strict_types=1);

namespace Shared\HouseMode\Strategy;

use Shared\HouseMode\TransitionContext;
use Stewart\Contracts\Schedule\TimeOfDay;

final readonly class FixedTimeStrategy implements ModeStrategy
{
    public function __construct(
        public string $name,
        public TimeOfDay $initialTime,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function attach(TransitionContext $context): StrategyTrigger
    {
        $trigger = new FixedTimeTrigger(
            $this,
            $context->exposeTime($this->name, $this->initialTime),
            $context->mode->scheduler,
        );
        $trigger->watchTimeCommands();

        return $trigger;
    }
}
