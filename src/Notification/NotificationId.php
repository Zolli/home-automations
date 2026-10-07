<?php

declare(strict_types=1);

namespace Shared\Notification;

final readonly class NotificationId
{
    private const string ALLOWED_PATTERN = '/^[A-Za-z0-9_-]+$/';
    private const string GENERATED_PREFIX = 'n-';
    private const int GENERATED_RANDOM_BYTES = 6;

    private function __construct(public string $value) {}

    public static function generate(): self
    {
        return new self(self::GENERATED_PREFIX . bin2hex(random_bytes(self::GENERATED_RANDOM_BYTES)));
    }

    /** @throws InvalidNotification */
    public static function fromString(string $value): self
    {
        return self::tryFromString($value) ?? throw InvalidNotification::forDisallowedIdCharacters($value);
    }

    public static function tryFromString(string $value): ?self
    {
        return preg_match(self::ALLOWED_PATTERN, $value) === 1 ? new self($value) : null;
    }
}
