<?php

declare(strict_types=1);

namespace Shared\HouseMode\Strategy;

use Shared\HouseMode\TransitionContext;
use Stewart\Contracts\Sun\SunEvent;
use Stewart\Contracts\Time\SunOffset;

final readonly class SunEventStrategy implements ModeStrategy
{
    public function __construct(
        public string $name,
        public SunEvent $event,
        public ?SunOffset $offset = null,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function attach(TransitionContext $context): StrategyTrigger
    {
        return new SunEventTrigger($this, $context->mode->scheduler);
    }
}
