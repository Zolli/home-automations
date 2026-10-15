<?php

declare(strict_types=1);

namespace Shared\HouseMode\Trigger;

use Shared\Config\InvalidTypedOptions;
use Shared\Config\TypedOptions;
use Stewart\Contracts\Sun\SunEvent;
use Stewart\Contracts\Time\Duration;
use Stewart\Contracts\Time\SunOffset;

final readonly class SunEventTriggerType implements ModeTriggerType
{
    public function getName(): string
    {
        return 'sun';
    }

    public function mapToTrigger(string $name, TypedOptions $options): ModeTrigger
    {
        return new SunEventTrigger($name, $this->mapSunEvent($options), $this->mapSunOffset($options));
    }

    /** @throws InvalidTypedOptions */
    private function mapSunEvent(TypedOptions $options): SunEvent
    {
        return SunEvent::tryFrom($options->requireString('event'))
            ?? throw InvalidTypedOptions::forInvalidOption(
                $options->kind,
                $options->type,
                'event',
                'a sun event like sunrise',
            );
    }

    /** @throws InvalidTypedOptions */
    private function mapSunOffset(TypedOptions $options): ?SunOffset
    {
        $offsetSeconds = $options->hasOption('offsetSeconds') ? $options->requireNumber('offsetSeconds') : 0.0;
        $magnitude     = Duration::seconds(abs($offsetSeconds));

        return match (true) {
            $offsetSeconds < 0.0 => SunOffset::before($magnitude),
            $offsetSeconds > 0.0 => SunOffset::after($magnitude),
            default              => null,
        };
    }
}
