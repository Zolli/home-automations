<?php

declare(strict_types=1);

namespace Shared\Config;

use InvalidArgumentException;

final class InvalidTypedOptions extends InvalidArgumentException
{
    public static function forMissingType(string $kind): self
    {
        return new self("{$kind} needs a \"type\".");
    }

    /** @param list<string> $knownTypes */
    public static function forUnknownType(string $kind, string $type, array $knownTypes): self
    {
        return new self(\sprintf('%s type "%s" is unknown, expected one of: %s.', $kind, $type, implode(', ', $knownTypes)));
    }

    public static function forInvalidOption(string $kind, string $type, string $key, string $expectation): self
    {
        return new self("{$kind} \"{$type}\" needs \"{$key}\" as {$expectation}.");
    }

    public static function forMissingOption(string $kind, string $type, string ...$keys): self
    {
        return new self(\sprintf('%s "%s" needs %s.', $kind, $type, implode(' or ', array_map(static fn(string $key): string => "\"{$key}\"", $keys))));
    }
}
