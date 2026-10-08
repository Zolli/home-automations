<?php

declare(strict_types=1);

namespace App\Tests\Shared\Alert\Mitigation;

use App\Tests\Shared\Alert\HaFake;
use PHPUnit\Framework\TestCase;
use Shared\Alert\Mitigation\TurnOffMitigation;
use Stewart\Contracts\Entity\EntityId;

final class TurnOffMitigationTest extends TestCase
{
    public function testClosesValve(): void
    {
        $fake = new HaFake($this);
        $mitigation = new TurnOffMitigation(new EntityId('valve.main_water'));

        $mitigation->mitigate($fake->ha);

        self::assertSame([['service' => 'valve.close_valve', 'data' => [], 'target' => ['entity_id' => ['valve.main_water']]]], $fake->calls);
        self::assertSame('Closed valve.main_water', $mitigation->describe());
    }

    public function testTurnsOffOtherEntities(): void
    {
        $fake = new HaFake($this);
        $mitigation = new TurnOffMitigation(new EntityId('switch.boiler_relay'));

        $mitigation->mitigate($fake->ha);

        self::assertSame([['service' => 'homeassistant.turn_off', 'data' => [], 'target' => ['entity_id' => ['switch.boiler_relay']]]], $fake->calls);
        self::assertSame('Turned off switch.boiler_relay', $mitigation->describe());
    }
}
