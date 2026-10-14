<?php

declare(strict_types=1);

namespace Shared\HouseMode\Strategy;

use Shared\Config\TypedOptions;

final readonly class FixedTimeStrategyType implements ModeStrategyType
{
    public function getName(): string
    {
        return 'fixed_time';
    }

    public function mapToStrategy(string $name, TypedOptions $options): ModeStrategy
    {
        return new FixedTimeStrategy($name, $options->requireTimeOfDay('time'));
    }
}
