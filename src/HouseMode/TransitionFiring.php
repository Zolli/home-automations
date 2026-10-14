<?php

declare(strict_types=1);

namespace Shared\HouseMode;

final readonly class TransitionFiring
{
    public function __construct(
        public string $transitionName,
        public string $strategyName,
    ) {}
}
