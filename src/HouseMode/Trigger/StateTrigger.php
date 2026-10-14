<?php

declare(strict_types=1);

namespace Shared\HouseMode\Trigger;

use Closure;
use Shared\HouseMode\TransitionContext;
use Stewart\Contracts\Entity\EntityId;
use Stewart\Contracts\Subscription;
use Stewart\Contracts\Time\Duration;

final class StateTrigger implements ModeTrigger
{
    private ?Subscription $stateChanges = null;

    public function __construct(
        private readonly string $name,
        private readonly EntityId $entityId,
        private readonly string $state,
        private readonly ?Duration $holdDuration = null,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function attach(TransitionContext $context): void {}

    public function arm(TransitionContext $context, Closure $whenFired): void
    {
        $this->disarm();
        $this->stateChanges = $context->mode->ha
            ->watchStateChanges($this->entityId)
            ->whenChangedTo($this->state, $this->holdDuration)
            ->subscribe(fn() => $whenFired($this->name));
    }

    public function disarm(): void
    {
        $this->stateChanges?->unsubscribe();
        $this->stateChanges = null;
    }

    public function detach(): void
    {
        $this->disarm();
    }

    public function describe(): string
    {
        $hold = $this->holdDuration === null ? '' : " for {$this->holdDuration}";

        return "{$this->entityId} turns {$this->state}{$hold}";
    }
}
