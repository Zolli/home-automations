<?php

declare(strict_types=1);

namespace Shared\Alert\Reading;

use Shared\Alert\Reading\Collection\ReadingCollection;
use Stewart\Contracts\Time\Duration;
use Stewart\Contracts\Time\Instant;

final readonly class ReadingSnapshot
{
    public function __construct(
        public ReadingCollection $readings,
        public Instant $takenAt,
        public Instant $watchingSince,
    ) {}

    public function find(ReadingSource $source): ?Reading
    {
        return $this->readings->find($source);
    }

    public function measureSilence(ReadingSource $source): Duration
    {
        $lastReceivedAt = $this->find($source)?->receivedAt;

        return $this->takenAt->elapsedSince($lastReceivedAt ?? $this->watchingSince);
    }
}
