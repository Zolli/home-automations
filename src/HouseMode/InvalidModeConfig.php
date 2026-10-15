<?php

declare(strict_types=1);

namespace Shared\HouseMode;

use InvalidArgumentException;
use Throwable;

final class InvalidModeConfig extends InvalidArgumentException
{
    public static function forInvalidTransition(string $name, string $reason, ?Throwable $previous = null): self
    {
        return new self("Mode transition \"{$name}\" is invalid: {$reason}", previous: $previous);
    }

    public static function forMissingStrategies(string $name): self
    {
        return new self("Mode transition \"{$name}\" needs at least one strategy.");
    }

    public static function forDuplicateStrategy(string $strategyName): self
    {
        return new self("Strategy \"{$strategyName}\" is defined more than once in a transition.");
    }

    public static function forUnknownDefaultStrategy(string $name, string $strategyName): self
    {
        return new self("Mode transition \"{$name}\" has no strategy \"{$strategyName}\" to use as default.");
    }

    public static function forInvalidWindow(string $name, ?Throwable $previous = null): self
    {
        return new self(
            "Mode transition \"{$name}\" needs a window with different \"from\" and \"until\" times like 18:00.",
            previous: $previous,
        );
    }
}
