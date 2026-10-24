<?php

declare(strict_types=1);

namespace App\Tests\Shared\Alert\Mitigation;

use PHPUnit\Framework\TestCase;
use Shared\Alert\Mitigation\TurnOffMitigation;
use Stewart\Contracts\Entity\EntityId;
use Stewart\Testing\HaContext\RecordingHaContext;

final class TurnOffMitigationTest extends TestCase
{
    public function testClosesValve(): void
    {
        $ha         = new RecordingHaContext();
        $mitigation = new TurnOffMitigation(new EntityId('valve.main_water'));

        $mitigation->mitigate($ha);

        self::assertCount(1, $ha->calls);
        self::assertSame('valve.close_valve', $ha->getLastCall()->getActionName());
        self::assertSame(['valve.main_water'], $ha->getLastCall()->listTargetedEntityIds());
        self::assertSame('Closed valve.main_water', $mitigation->describe());
    }

    public function testTurnsOffOtherEntities(): void
    {
        $ha         = new RecordingHaContext();
        $mitigation = new TurnOffMitigation(new EntityId('switch.boiler_relay'));

        $mitigation->mitigate($ha);

        self::assertCount(1, $ha->calls);
        self::assertSame('homeassistant.turn_off', $ha->getLastCall()->getActionName());
        self::assertSame(['switch.boiler_relay'], $ha->getLastCall()->listTargetedEntityIds());
        self::assertSame('Turned off switch.boiler_relay', $mitigation->describe());
    }
}
