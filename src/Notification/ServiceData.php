<?php

declare(strict_types=1);

namespace Shared\Notification;

final class ServiceData
{
    /**
     * @param array<array-key, mixed> $base
     * @param array<array-key, mixed> ...$overrides
     * @return array<array-key, mixed>
     */
    public static function mergeReplacingLists(array $base, array ...$overrides): array
    {
        foreach ($overrides as $override) {
            foreach ($override as $key => $value) {
                $current = $base[$key] ?? null;
                $base[$key] = self::isMap($current) && self::isMap($value)
                    ? self::mergeReplacingLists($current, $value)
                    : $value;
            }
        }

        return $base;
    }

    /** @phpstan-assert-if-true array<array-key, mixed> $value */
    private static function isMap(mixed $value): bool
    {
        return \is_array($value) && !array_is_list($value);
    }
}
