<?php

declare(strict_types=1);

namespace Shared\HouseMode\Trigger;

use Closure;
use Shared\HouseMode\TransitionContext;
use Stewart\Contracts\Exception\ExposureException;

interface ModeTrigger
{
    public function getName(): string;

    /** @throws ExposureException */
    public function attach(TransitionContext $context): void;

    /** @param Closure(string): void $whenFired */
    public function arm(TransitionContext $context, Closure $whenFired): void;

    public function disarm(): void;

    public function detach(): void;

    public function describe(): string;
}
