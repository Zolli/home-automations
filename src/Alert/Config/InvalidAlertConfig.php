<?php

declare(strict_types=1);

namespace Shared\Alert\Config;

use InvalidArgumentException;
use Throwable;

final class InvalidAlertConfig extends InvalidArgumentException
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

    public static function forDuplicateType(string $kind, string $type): self
    {
        return new self("{$kind} type \"{$type}\" is registered twice.");
    }

    public static function forInvalidOption(string $kind, string $type, string $key, string $expectation): self
    {
        return new self("{$kind} \"{$type}\" needs \"{$key}\" as {$expectation}.");
    }

    public static function forMissingSource(string $kind, string $type, string ...$keys): self
    {
        return new self(\sprintf('%s "%s" needs one of: %s.', $kind, $type, implode(', ', $keys)));
    }

    public static function forInvalidPolicy(string $name, string $reason, ?Throwable $previous = null): self
    {
        return new self("Escalation policy \"{$name}\" is invalid: {$reason}", previous: $previous);
    }

    public static function forInvalidAlert(string $id, string $reason, ?Throwable $previous = null): self
    {
        return new self("Alert \"{$id}\" is invalid: {$reason}", previous: $previous);
    }

    public static function forDuplicateAlert(string $id): self
    {
        return new self("Alert \"{$id}\" is defined twice.");
    }

    public static function forMissingAlertId(): self
    {
        return new self('Alert needs a non-empty "id".');
    }
}
