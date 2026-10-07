<?php

declare(strict_types=1);

namespace Shared\Notification\Payload;

final readonly class PayloadViolation
{
    private const string ROOT_PATH = 'payload';

    private function __construct(
        public string $path,
        public string $reason,
    ) {}

    /** @param list<string|int> $segments */
    public static function atPath(array $segments, string $reason): self
    {
        $path = '';

        foreach ($segments as $segment) {
            $path .= \is_int($segment) ? "[{$segment}]" : ($path === '' ? $segment : ".{$segment}");
        }

        return new self($path === '' ? self::ROOT_PATH : $path, $reason);
    }

    public function toString(): string
    {
        return "{$this->path} {$this->reason}";
    }
}
