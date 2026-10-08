<?php

declare(strict_types=1);

namespace App\Tests\Shared\Alert\Reading;

use App\Tests\Shared\Alert\HaFake;
use PHPUnit\Framework\TestCase;
use Shared\Alert\Reading\Collection\ReadingSourceCollection;
use Shared\Alert\Reading\ReadingFeed;
use Shared\Alert\Reading\ReadingSource;
use Stewart\Contracts\Entity\EntityId;

final class ReadingFeedTest extends TestCase
{
    private const string TOPIC = 'dsmr/reading/phase_voltage_l1';

    private HaFake $fake;
    private ReadingFeed $feed;
    private int $changes = 0;

    protected function setUp(): void
    {
        $this->fake = new HaFake($this);
        $this->feed = new ReadingFeed(
            ReadingSourceCollection::fromSources([ReadingSource::forEntity(new EntityId('sensor.l1_voltage')), ReadingSource::forTopic(self::TOPIC)]),
            $this->fake->createMonitoringContext(),
        );
    }

    public function testReadsEntityStatesAndLatestPayloads(): void
    {
        $this->fake->setState('sensor.l1_voltage', '231', ['friendly_name' => 'L1']);
        $this->startWatching();

        $this->fake->publishMqtt(self::TOPIC, '230.5');
        $this->fake->publishMqtt(self::TOPIC, " 12.0\n");

        $snapshot = $this->feed->readSnapshot();
        self::assertSame('L1', $snapshot->find(ReadingSource::forEntity(new EntityId('sensor.l1_voltage')))?->label);
        self::assertSame(12.0, $snapshot->find(ReadingSource::forTopic(self::TOPIC))?->readNumber());
        self::assertSame(2, $this->changes);
    }

    public function testHasNoTopicReadingBeforeFirstMessage(): void
    {
        $this->startWatching();

        self::assertNull($this->feed->readSnapshot()->find(ReadingSource::forTopic(self::TOPIC)));
    }

    public function testStopWatchingUnsubscribesAndForgetsPayloads(): void
    {
        $this->startWatching();
        $this->fake->publishMqtt(self::TOPIC, '230');

        $this->feed->stopWatching();

        self::assertFalse($this->fake->isWatchingTopic(self::TOPIC));
        self::assertFalse($this->fake->isWatchingStates());
        self::assertNull($this->feed->readSnapshot()->find(ReadingSource::forTopic(self::TOPIC)));
    }

    private function startWatching(): void
    {
        $this->feed->startWatching(function (): void {
            $this->changes++;
        });
    }
}
