<?php

declare(strict_types=1);

namespace Shared\HouseMode\Trigger;

use Shared\Config\TypedOptions;

final readonly class FixedTimeTriggerType implements ModeTriggerType
{
    public function getName(): string
    {
        return 'fixed_time';
    }

    public function mapToTrigger(string $name, TypedOptions $options): ModeTrigger
    {
        return new FixedTimeTrigger($name, $options->requireTimeOfDay('time'));
    }
}
