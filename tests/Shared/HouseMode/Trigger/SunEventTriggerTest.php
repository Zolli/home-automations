<?php

declare(strict_types=1);

namespace App\Tests\Shared\HouseMode\Trigger;

use App\Tests\Shared\HouseMode\FiringRecorder;
use App\Tests\Shared\HouseMode\ModeFixture;
use PHPUnit\Framework\TestCase;
use Shared\HouseMode\Trigger\SunEventTrigger;
use Stewart\Contracts\Sun\SunEvent;
use Stewart\Contracts\Time\Duration;
use Stewart\Contracts\Time\SunOffset;

final class SunEventTriggerTest extends TestCase
{
    private FiringRecorder $recorder;

    protected function setUp(): void
    {
        $this->recorder = new FiringRecorder();
    }

    public function testFiresAtSunrise(): void
    {
        $fixture = new ModeFixture('2026-10-14 05:00');
        new SunEventTrigger('sun_above_horizon', SunEvent::Sunrise)
            ->arm($fixture->createTransitionContext('day'), $this->recorder->recordFiring(...));

        $fixture->advanceTime(Duration::minutes(59));
        self::assertSame([], $this->recorder->firedStrategies);

        $fixture->advanceTime(Duration::minutes(1));

        self::assertSame(['sun_above_horizon'], $this->recorder->firedStrategies);
    }

    public function testFiresBeforeSunsetWithOffset(): void
    {
        $fixture = new ModeFixture('2026-10-14 17:00');
        $trigger = new SunEventTrigger('dusk', SunEvent::Sunset, SunOffset::before(Duration::minutes(30)));
        $trigger->arm($fixture->createTransitionContext('night'), $this->recorder->recordFiring(...));

        $fixture->advanceTime(Duration::minutes(30));

        self::assertSame(['dusk'], $this->recorder->firedStrategies);
        self::assertStringStartsWith('at sunset -', $trigger->describe());
    }
}
