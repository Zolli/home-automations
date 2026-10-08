<?php

declare(strict_types=1);

namespace Shared\Alert\Rule;

use Shared\Alert\Reading\Collection\ReadingSourceCollection;
use Shared\Alert\Reading\ReadingSnapshot;
use Stewart\Contracts\Time\Duration;

final readonly class ThresholdRule implements AlertRule
{
    public function __construct(
        private ReadingSourceCollection $sources,
        private ThresholdDirection $direction,
        private float $threshold,
        private float $hysteresis,
        private Duration $holdDuration,
        private ?string $attribute = null,
        private bool $unavailableIsFault = false,
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

            if ($reading === null) {
                continue;
            }

            if ($this->unavailableIsFault && $reading->isUnavailable()) {
                return Fault::fromReading($reading, "{$reading->label} is {$reading->value}");
            }

            $value = $reading->readNumber($this->attribute);

            if ($value !== null && $this->direction->isBeyond($value, $this->threshold)) {
                return Fault::fromReading($reading, \sprintf('%s is %s, %s %s', $reading->label, $value, $this->direction->value, $this->threshold));
            }
        }

        return null;
    }

    public function isRecovered(ReadingSnapshot $snapshot): bool
    {
        $hasNumericReading = false;

        foreach ($this->sources as $source) {
            $reading = $snapshot->find($source);

            if ($reading === null) {
                continue;
            }

            if ($this->unavailableIsFault && $reading->isUnavailable()) {
                return false;
            }

            $value = $reading->readNumber($this->attribute);

            if ($value === null) {
                continue;
            }

            if (!$this->direction->hasReturned($value, $this->threshold, $this->hysteresis)) {
                return false;
            }

            $hasNumericReading = true;
        }

        return $hasNumericReading;
    }

    public function describe(): string
    {
        $subject = $this->attribute === null ? $this->sources->describe() : "{$this->sources->describe()}[{$this->attribute}]";
        $unavailable = $this->unavailableIsFault ? ' or unavailable' : '';

        return "{$subject} {$this->direction->value} {$this->threshold}{$unavailable}";
    }
}
