<?php

declare(strict_types=1);

namespace App\Tests\Shared\Alert\Signal;

use App\Tests\Shared\Alert\HaFake;
use PHPUnit\Framework\TestCase;
use Shared\Alert\FaultLocator;
use Shared\Alert\Reading\ReadingSource;
use Shared\Alert\Rule\Fault;
use Shared\Alert\Signal\LightEffectSignal;
use Stewart\Contracts\Entity\EntityId;

final class LightEffectSignalTest extends TestCase
{
    private const string SMOKE_SENSOR = 'binary_sensor.smoke_detector_kitchen_smoke';

    private HaFake $fake;
    private LightEffectSignal $signal;

    protected function setUp(): void
    {
        $this->fake = new HaFake($this);
        $this->signal = new LightEffectSignal('okay', new FaultLocator());
        $this->fake->placeInArea(self::SMOKE_SENSOR, 'kitchen', 'Kitchen');
    }

    public function testRunsEffectOnLightsInFaultAreaSupportingIt(): void
    {
        $this->placeLight('light.kitchen_all', 'kitchen', ['okay', 'blink']);
        $this->placeLight('light.kitchen_strip', 'kitchen', ['colorloop']);
        $this->placeLight('light.kitchen_plain', 'kitchen', null);
        $this->placeLight('light.hallway_ceiling', 'hallway', ['okay']);

        $this->signal->emit($this->createFault(self::SMOKE_SENSOR), $this->fake->ha);

        self::assertSame(
            [['service' => 'light.turn_on', 'data' => ['effect' => 'okay'], 'target' => ['entity_id' => ['light.kitchen_all']]]],
            $this->fake->calls,
        );
    }

    public function testSkipsAreaWithoutSupportingLights(): void
    {
        $this->placeLight('light.kitchen_strip', 'kitchen', ['colorloop']);

        $this->signal->emit($this->createFault(self::SMOKE_SENSOR), $this->fake->ha);

        self::assertSame([], $this->fake->calls);
    }

    public function testSkipsFaultWithoutArea(): void
    {
        $this->placeLight('light.kitchen_all', 'kitchen', ['okay']);

        $this->signal->emit($this->createFault('binary_sensor.leak_garage'), $this->fake->ha);

        self::assertSame([], $this->fake->calls);
    }

    /** @param ?list<string> $effects */
    private function placeLight(string $entityId, string $areaId, ?array $effects): void
    {
        $this->fake->placeInArea($entityId, $areaId, ucfirst($areaId));
        $this->fake->setState($entityId, 'off', $effects === null ? [] : ['effect_list' => $effects]);
    }

    private function createFault(string $entityId): Fault
    {
        return new Fault(ReadingSource::forEntity(new EntityId($entityId)), $entityId, "{$entityId} is on");
    }
}
