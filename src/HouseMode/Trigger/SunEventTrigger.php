<?php

declare(strict_types=1);

namespace Shared\HouseMode\Trigger;

use Closure;
use Shared\HouseMode\TransitionContext;
use Stewart\Contracts\Schedule\ScheduledTask;
use Stewart\Contracts\Sun\SunEvent;
use Stewart\Contracts\Time\SunOffset;

final class SunEventTrigger implements ModeTrigger
{
    private ?ScheduledTask $sunEventRun = null;

    public function __construct(
        private readonly string $name,
        private readonly SunEvent $event,
        private readonly ?SunOffset $offset = null,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function attach(TransitionContext $context): void {}

    public function arm(TransitionContext $context, Closure $whenFired): void
    {
        $this->disarm();
        $this->sunEventRun = $context->mode->scheduler->runAtSunEvent(
            $this->event,
            fn() => $whenFired($this->name),
            $this->offset,
        );
    }

    public function disarm(): void
    {
        $this->sunEventRun?->cancel();
        $this->sunEventRun = null;
    }

    public function detach(): void
    {
        $this->disarm();
    }

    public function describe(): string
    {
        $offset = $this->offset === null ? '' : " {$this->offset->formatAsHaOffset()}";

        return "at {$this->event->value}{$offset}";
    }
}
