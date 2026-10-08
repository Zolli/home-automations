<?php

declare(strict_types=1);

namespace Shared\Alert\Rule;

use Shared\Alert\Reading\Collection\ReadingSourceCollection;
use Shared\Alert\Reading\ReadingSnapshot;
use Stewart\Contracts\Time\Duration;

final readonly class StateMatchRule implements AlertRule
{
    public function __construct(
        private ReadingSourceCollection $sources,
        private string $faultState,
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

            if ($reading?->value === $this->faultState) {
                return Fault::fromReading($reading, "{$reading->label} is {$this->faultState}");
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
        return "any of {$this->sources->describe()} is {$this->faultState}";
    }
}
