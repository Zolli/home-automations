<?php

declare(strict_types=1);

namespace Shared\Alert\Reading\Collection;

use Shared\Alert\Reading\ReadingSource;
use Shared\Alert\Reading\ReadingSourceType;
use Stewart\Contracts\Collection\ListCollection;

/** @extends ListCollection<ReadingSource> */
final readonly class ReadingSourceCollection extends ListCollection
{
    /** @param iterable<ReadingSource> $sources */
    public static function fromSources(iterable $sources): self
    {
        return self::fromList($sources);
    }

    /** @return list<string> */
    public function listKeysOfType(ReadingSourceType $type): array
    {
        return $this->filter(static fn(ReadingSource $source): bool => $source->type === $type)
            ->mapToList(static fn(ReadingSource $source): string => $source->key);
    }

    public function describe(): string
    {
        return implode(', ', $this->mapToList(static fn(ReadingSource $source): string => $source->key));
    }
}
