<?php

declare(strict_types=1);

namespace Shared\HouseMode\Strategy;

use Shared\Config\InvalidTypedOptions;
use Shared\Config\TypedOptions;
use Stewart\Contracts\Sun\SunEvent;
use Stewart\Contracts\Time\Duration;
use Stewart\Contracts\Time\SunOffset;

final readonly class SunEventStrategyType implements ModeStrategyType
{
    private const string OFFSET_KEY = 'offsetSeconds';

    public function getName(): string
    {
        return 'sun';
    }

    public function mapToStrategy(string $name, TypedOptions $options): ModeStrategy
    {
        $event = SunEvent::tryFrom($options->requireString('event'))
            ?? throw InvalidTypedOptions::forInvalidOption(
                $options->kind,
                $options->type,
                'event',
                'a sun event like sunrise',
            );

        return new SunEventStrategy($name, $event, $this->mapOffset($options));
    }

    /** @throws InvalidTypedOptions */
    private function mapOffset(TypedOptions $options): ?SunOffset
    {
        $offsetSeconds = $options->hasOption(self::OFFSET_KEY) ? $options->requireNumber(self::OFFSET_KEY) : 0.0;
        $magnitude     = Duration::seconds(abs($offsetSeconds));

        return match (true) {
            $offsetSeconds < 0.0 => SunOffset::before($magnitude),
            $offsetSeconds > 0.0 => SunOffset::after($magnitude),
            default              => null,
        };
    }
}
