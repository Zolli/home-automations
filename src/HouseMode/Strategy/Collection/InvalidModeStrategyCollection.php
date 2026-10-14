<?php

declare(strict_types=1);

namespace Shared\HouseMode\Strategy\Collection;

use InvalidArgumentException;

final class InvalidModeStrategyCollection extends InvalidArgumentException
{
    public static function forDuplicateName(string $name): self
    {
        return new self("Strategy \"{$name}\" is defined more than once.");
    }
}
