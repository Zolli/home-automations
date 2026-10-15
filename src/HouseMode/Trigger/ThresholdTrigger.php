<?php

declare(strict_types=1);

namespace Shared\HouseMode\Trigger;

use Closure;
use Shared\HouseMode\TransitionContext;
use Shared\Threshold\ThresholdDirection;
use Stewart\Contracts\Entity\EntityId;
use Stewart\Contracts\Exposure\Command\NumberCommand;
use Stewart\Contracts\Subscription;
use Stewart\Contracts\Time\Duration;

final class ThresholdTrigger implements ModeTrigger
{
    private float $threshold;

    private ?Subscription $crossings = null;

    private ?Subscription $thresholdCommands = null;

    /** @var (Closure(): void)|null */
    private ?Closure $rearm = null;

    public function __construct(
        private readonly string $name,
        private readonly EntityId $entityId,
        private readonly ThresholdDirection $direction,
        private readonly float $initialThreshold,
        private readonly ThresholdRange $range,
        private readonly float $hysteresis = 0.0,
        private readonly ?Duration $holdDuration = null,
    ) {
        $this->threshold = $initialThreshold;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function attach(TransitionContext $context): void
    {
        $exposedThreshold        = $context->exposeThreshold($this->name, $this->range, $this->initialThreshold);
        $this->threshold         = (float) ($exposedThreshold->getValue() ?? $this->initialThreshold);
        $this->thresholdCommands = $exposedThreshold->watchCommands()->subscribe($this->changeThreshold(...));
    }

    public function arm(TransitionContext $context, Closure $whenFired): void
    {
        $this->disarm();
        $this->crossings = $this->direction
            ->filterCrossings(
                $context->mode->ha->watchStateChanges($this->entityId),
                $this->threshold,
                $this->holdDuration,
                $this->hysteresis,
            )
            ->subscribe(fn() => $whenFired($this->name));
        $this->rearm = fn() => $this->arm($context, $whenFired);
    }

    public function disarm(): void
    {
        $this->crossings?->unsubscribe();
        $this->crossings = null;
        $this->rearm     = null;
    }

    public function detach(): void
    {
        $this->disarm();
        $this->thresholdCommands?->unsubscribe();
        $this->thresholdCommands = null;
    }

    public function describe(): string
    {
        $hold = $this->holdDuration === null ? '' : " for {$this->holdDuration}";

        return "{$this->entityId} {$this->direction->value} {$this->threshold}{$hold}";
    }

    private function changeThreshold(NumberCommand $command): void
    {
        $this->threshold = (float) $command->value;

        if ($this->rearm !== null) {
            ($this->rearm)();
        }
    }
}
