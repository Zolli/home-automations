<?php

declare(strict_types=1);

namespace Shared\Notification;

enum Importance: string
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Critical = 'critical';

    public const self DEFAULT = self::Normal;

    public function isAtLeast(self $other): bool
    {
        return $this->getRank() >= $other->getRank();
    }

    private function getRank(): int
    {
        return match ($this) {
            self::Low => 0,
            self::Normal => 1,
            self::High => 2,
            self::Critical => 3,
        };
    }
}
