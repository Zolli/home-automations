<?php

declare(strict_types=1);

namespace App\Tests\Shared\Alert\Rule;

use PHPUnit\Framework\TestCase;
use Shared\Alert\Reading\Collection\ReadingCollection;
use Shared\Alert\Reading\Collection\ReadingSourceCollection;
use Shared\Alert\Reading\Reading;
use Shared\Alert\Reading\ReadingSnapshot;
use Shared\Alert\Reading\ReadingSource;
use Shared\Alert\Rule\SilenceRule;
use Stewart\Contracts\Time\Duration;
use Stewart\Contracts\Time\Instant;

final class SilenceRuleTest extends TestCase
{
    private const string L1 = 'dsmr/reading/phase_voltage_l1';
    private const string L2 = 'dsmr/reading/phase_voltage_l2';

    public function testFindsTopicSilentTooLong(): void
    {
        $fault = $this->createRule()->findFault($this->createSnapshot(l1SecondsAgo: 30, l2SecondsAgo: 121));

        self::assertSame(self::L2, $fault?->source->key);
        self::assertSame('dsmr/reading/phase_voltage_l2 sent nothing for 121 s', $fault->reason);
    }

    public function testCountsSilenceSinceWatchingWhenNeverReceived(): void
    {
        $rule = $this->createRule();

        self::assertNull($rule->findFault($this->createSnapshot(l1SecondsAgo: 5, l2SecondsAgo: null, watchingSecondsAgo: 60)));
        self::assertNotNull($rule->findFault($this->createSnapshot(l1SecondsAgo: 5, l2SecondsAgo: null, watchingSecondsAgo: 121)));
    }

    public function testRecoversWhenAllTopicsAreRecent(): void
    {
        $rule = $this->createRule();

        self::assertTrue($rule->isRecovered($this->createSnapshot(l1SecondsAgo: 1, l2SecondsAgo: 120)));
        self::assertFalse($rule->isRecovered($this->createSnapshot(l1SecondsAgo: 1, l2SecondsAgo: 300)));
    }

    public function testRechecksTenTimesPerSilenceWindow(): void
    {
        self::assertSame(12_000, $this->createRule()->getRecheckInterval()->toMilliseconds());
    }

    private function createRule(): SilenceRule
    {
        return new SilenceRule(
            ReadingSourceCollection::fromSources([ReadingSource::forTopic(self::L1), ReadingSource::forTopic(self::L2)]),
            Duration::seconds(120),
            Duration::zero(),
        );
    }

    private function createSnapshot(int $l1SecondsAgo, ?int $l2SecondsAgo, int $watchingSecondsAgo = 3600): ReadingSnapshot
    {
        $now = Instant::fromEpochMicroseconds(1_800_000_000_000_000);
        $readings = [Reading::fromMqttPayload(self::L1, '230', $now->minus(Duration::seconds($l1SecondsAgo)))];

        if ($l2SecondsAgo !== null) {
            $readings[] = Reading::fromMqttPayload(self::L2, '230', $now->minus(Duration::seconds($l2SecondsAgo)));
        }

        return new ReadingSnapshot(ReadingCollection::keyedBySource($readings), $now, $now->minus(Duration::seconds($watchingSecondsAgo)));
    }
}
