<?php

declare(strict_types=1);

namespace Shared\HouseMode;

use Shared\HouseMode\Strategy\Collection\ModeStrategyCollection;

final readonly class ModeTransition
{
    public function __construct(
        public string $name,
        public ModeStrategyCollection $strategies,
        public string $defaultStrategyName,
        public ModeStrategyCollection $alwaysActive,
        public ?TransitionWindow $window = null,
    ) {}

    public function resolveStrategyName(?string $selectedName): string
    {
        return $selectedName !== null && $this->strategies->find($selectedName) !== null
            ? $selectedName
            : $this->defaultStrategyName;
    }
}
