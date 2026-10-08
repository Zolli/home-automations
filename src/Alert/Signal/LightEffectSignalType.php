<?php

declare(strict_types=1);

namespace Shared\Alert\Signal;

use Shared\Alert\Config\TypedOptions;
use Shared\Alert\FaultLocator;

final readonly class LightEffectSignalType implements SignalType
{
    public function __construct(private FaultLocator $locator) {}

    public function getName(): string
    {
        return 'light-effect';
    }

    public function mapToSignal(TypedOptions $options): Signal
    {
        return new LightEffectSignal($options->requireString('effect'), $this->locator);
    }
}
