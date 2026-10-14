<?php

declare(strict_types=1);

namespace Shared\HouseMode\Strategy;

use Shared\Config\InvalidTypedOptions;
use Shared\Config\NamedType;
use Shared\Config\TypedOptions;

interface ModeStrategyType extends NamedType
{
    public const string KIND     = 'Mode strategy';
    public const string HOLD_KEY = 'forSeconds';

    /** @throws InvalidTypedOptions */
    public function mapToStrategy(string $name, TypedOptions $options): ModeStrategy;
}
