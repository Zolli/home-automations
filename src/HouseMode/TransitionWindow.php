<?php

declare(strict_types=1);

namespace Shared\HouseMode;

use Stewart\Contracts\Schedule\TimeOfDay;
use Stewart\Contracts\Time\Clock;

final readonly class TransitionWindow
{
    public function __construct(
        public TimeOfDay $from,
        public TimeOfDay $until,
    ) {}

    public function isOpen(Clock $clock): bool
    {
        return $clock->isWithin($this->from, $this->until);
    }

    public function describe(): string
    {
        return "{$this->from->format()}-{$this->until->format()}";
    }
}
