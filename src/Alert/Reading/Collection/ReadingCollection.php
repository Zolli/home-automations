<?php

declare(strict_types=1);

namespace Shared\Alert\Reading\Collection;

use Shared\Alert\Reading\Reading;
use Shared\Alert\Reading\ReadingSource;
use Stewart\Contracts\Collection\KeyedCollection;

/** @extends KeyedCollection<string, Reading> */
final readonly class ReadingCollection extends KeyedCollection
{
    /** @param iterable<Reading> $readings */
    public static function keyedBySource(iterable $readings): self
    {
        $readingsBySource = [];

        foreach ($readings as $reading) {
            $readingsBySource[self::createKey($reading->source)] = $reading;
        }

        return self::fromElementsByKey($readingsBySource);
    }

    public function find(ReadingSource $source): ?Reading
    {
        return $this->elementAt(self::createKey($source));
    }

    private static function createKey(ReadingSource $source): string
    {
        return "{$source->type->value}:{$source->key}";
    }
}
