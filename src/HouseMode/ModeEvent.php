<?php

declare(strict_types=1);

namespace Shared\HouseMode;

final readonly class ModeEvent
{
    public function __construct(
        public string $event,
        public string $cause,
        public string $producer,
    ) {}
}
