<?php

declare(strict_types=1);

namespace App\Tests\Shared\HouseMode;

use PHPUnit\Framework\TestCase;
use Shared\HouseMode\TransitionWindow;
use Stewart\Contracts\Schedule\TimeOfDay;
use Stewart\Contracts\Time\Duration;

final class TransitionWindowTest extends TestCase
{
    public function testOpensAcrossMidnight(): void
    {
        $fixture = new ModeFixture('2026-10-14 17:59');
        $window  = new TransitionWindow(TimeOfDay::parse('18:00'), TimeOfDay::parse('03:00'));

        self::assertFalse($window->isOpen($fixture->harness->clock));

        $fixture->advanceTime(Duration::minutes(1));
        self::assertTrue($window->isOpen($fixture->harness->clock));

        $fixture->advanceTime(Duration::hours(8));
        self::assertTrue($window->isOpen($fixture->harness->clock));
    }

    public function testClosesAtUntilTime(): void
    {
        $fixture = new ModeFixture('2026-10-15 03:00');
        $window  = new TransitionWindow(TimeOfDay::parse('18:00'), TimeOfDay::parse('03:00'));

        self::assertFalse($window->isOpen($fixture->harness->clock));
        self::assertSame('18:00:00-03:00:00', $window->describe());
    }
}
