<?php

declare(strict_types=1);

namespace Shared\HouseMode\Strategy;

use Shared\Config\TypedOptions;

final readonly class StateStrategyType implements ModeStrategyType
{
    public function getName(): string
    {
        return 'state';
    }

    public function mapToStrategy(string $name, TypedOptions $options): ModeStrategy
    {
        return new StateStrategy(
            $name,
            $options->requireEntityId('entity'),
            $options->findString('state') ?? 'on',
            $options->findPositiveDuration(self::HOLD_KEY),
        );
    }
}
