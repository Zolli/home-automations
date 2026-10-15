<?php

declare(strict_types=1);

namespace Shared\HouseMode\Trigger;

use Shared\Config\TypedOptions;

final readonly class StateTriggerType implements ModeTriggerType
{
    public function getName(): string
    {
        return 'state';
    }

    public function mapToTrigger(string $name, TypedOptions $options): ModeTrigger
    {
        return new StateTrigger(
            $name,
            $options->requireEntityId('entity'),
            $options->findString('state') ?? 'on',
            $options->findPositiveDuration(self::HOLD_KEY),
        );
    }
}
