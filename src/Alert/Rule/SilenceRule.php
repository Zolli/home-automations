<?php

declare(strict_types=1);

namespace Shared\Alert\Rule;

use Shared\Alert\Reading\Collection\ReadingSourceCollection;
use Shared\Alert\Reading\ReadingSnapshot;
use Stewart\Contracts\Time\Duration;

final readonly class SilenceRule implements RecheckedAlertRule
{
    private const int RECHECKS_PER_SILENCE = 10;

    private const int MIN_RECHECK_SECONDS = 1;

    public function __construct(
        private ReadingSourceCollection $topicSources,
        private Duration $maxSilence,
        private Duration $holdDuration,
    ) {}

    public function listSources(): ReadingSourceCollection
    {
        return $this->topicSources;
    }

    public function getHoldDuration(): Duration
    {
        return $this->holdDuration;
    }

    public function getRecheckInterval(): Duration
    {
        return Duration::seconds(max(self::MIN_RECHECK_SECONDS, $this->maxSilence->toSeconds() / self::RECHECKS_PER_SILENCE));
    }

    public function findFault(ReadingSnapshot $snapshot): ?Fault
    {
        foreach ($this->topicSources as $source) {
            $silence = $snapshot->measureSilence($source);

            if ($silence->isLongerThan($this->maxSilence)) {
                return new Fault($source, $source->key, \sprintf('%s sent nothing for %d s', $source->key, $silence->toSeconds()));
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
        return \sprintf('any of %s silent for over %d s', $this->topicSources->describe(), $this->maxSilence->toSeconds());
    }
}
