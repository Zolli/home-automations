<?php

declare(strict_types=1);

namespace Shared\HouseMode\Strategy;

use Shared\HouseMode\TransitionContext;
use Stewart\Contracts\Exception\ExposureException;

interface ModeStrategy
{
    public function getName(): string;

    /** @throws ExposureException */
    public function attach(TransitionContext $context): StrategyTrigger;
}
