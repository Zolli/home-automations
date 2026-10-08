<?php

declare(strict_types=1);

namespace App\Tests\Shared\Alert\Rule;

use Shared\Alert\Reading\Collection\ReadingCollection;
use Shared\Alert\Reading\Collection\ReadingSourceCollection;
use Shared\Alert\Reading\Reading;
use Shared\Alert\Reading\ReadingSnapshot;
use Shared\Alert\Reading\ReadingSource;
use Stewart\Contracts\Entity\EntityId;
use Stewart\Contracts\State\EntityState;
use Stewart\Contracts\Time\Instant;

final class ReadingFixture
{
    private const int NOW = 1_800_000_000_000_000;

    public static function createEntitySources(string ...$entityIds): ReadingSourceCollection
    {
        return ReadingSourceCollection::fromSources(array_map(static fn(string $id) => ReadingSource::forEntity(new EntityId($id)), $entityIds));
    }

    /** @param array<string, mixed> $attributes */
    public static function createEntityReading(string $entityId, string $state, array $attributes = []): Reading
    {
        return Reading::fromEntityState(new EntityState(new EntityId($entityId), $state, $attributes));
    }

    public static function collectReadings(Reading ...$readings): ReadingSnapshot
    {
        $now = Instant::fromEpochMicroseconds(self::NOW);

        return new ReadingSnapshot(ReadingCollection::keyedBySource($readings), $now, $now);
    }
}
