<?php

declare(strict_types=1);

namespace Shared\Alert\Rule;

use Shared\Alert\Reading\Collection\ReadingSourceCollection;
use Shared\Alert\Reading\ReadingSnapshot;
use Stewart\Contracts\Time\Duration;

final readonly class UnavailableRule implements AlertRule
{
    public function __construct(
        private ReadingSourceCollection $sources,
        private Duration $holdDuration,
    ) {}

    public function listSources(): ReadingSourceCollection
    {
        return $this->sources;
    }

    public function getHoldDuration(): Duration
    {
        return $this->holdDuration;
    }

    public function findFault(ReadingSnapshot $snapshot): ?Fault
    {
        foreach ($this->sources as $source) {
            $reading = $snapshot->find($source);

            if ($reading?->isUnavailable()) {
                return Fault::fromReading($reading, "{$reading->label} is {$reading->value}");
            }
        }

        return null;
    }

    public function isRecovered(ReadingSnapshot $snapshot): bool
    {
        return $this->findFault($snapshot) === null;
    }

    public function describe(): string
    {
        return "any of {$this->sources->describe()} is unavailable";
    }
}
