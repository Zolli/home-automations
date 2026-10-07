<?php

declare(strict_types=1);

namespace Shared\Condition;

enum Operator: string
{
    case Eq = 'eq';
    case Neq = 'neq';
    case Gt = 'gt';
    case Gte = 'gte';
    case Lt = 'lt';
    case Lte = 'lte';

    public function requiresNumericValue(): bool
    {
        return match ($this) {
            self::Gt, self::Gte, self::Lt, self::Lte => true,
            self::Eq, self::Neq => false,
        };
    }

    public function compare(mixed $actual, mixed $expected): bool
    {
        if (is_numeric($actual) && is_numeric($expected)) {
            $actual = (float) $actual;
            $expected = (float) $expected;

            return match ($this) {
                self::Eq => $actual === $expected,
                self::Neq => $actual !== $expected,
                self::Gt => $actual > $expected,
                self::Gte => $actual >= $expected,
                self::Lt => $actual < $expected,
                self::Lte => $actual <= $expected,
            };
        }

        return match ($this) {
            self::Eq => self::normalize($actual) === self::normalize($expected),
            self::Neq => self::normalize($actual) !== self::normalize($expected),
            default => false,
        };
    }

    private static function normalize(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            \is_bool($value) => $value ? 'true' : 'false',
            \is_scalar($value) => (string) $value,
            default => json_encode($value, \JSON_THROW_ON_ERROR),
        };
    }
}
