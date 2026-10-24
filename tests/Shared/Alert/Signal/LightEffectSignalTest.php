<?php

declare(strict_types=1);

namespace App\Tests\Shared\Alert\Signal;

use App\Tests\Shared\Alert\AlertFixture;
use PHPUnit\Framework\TestCase;
use Shared\Alert\FaultLocator;
use Shared\Alert\Reading\ReadingSource;
use Shared\Alert\Rule\Fault;
use Shared\Alert\Signal\LightEffectSignal;
use Stewart\Contracts\Entity\EntityId;

final class LightEffectSignalTest extends TestCase
{
    private const string SMOKE_SENSOR = 'binary_sensor.smoke_detector_kitchen_smoke';

    private AlertFixture $fixture;

    private LightEffectSignal $signal;

    protected function setUp(): void
    {
        $this->fixture = new AlertFixture();
        $this->signal  = new LightEffectSignal('okay', new FaultLocator());
        $this->fixture->placeInArea(self::SMOKE_SENSOR, 'kitchen', 'Kitchen');
    }

    public function testRunsEffectOnLightsInFaultAreaSupportingIt(): void
    {
        $this->placeLight('light.kitchen_all', 'kitchen', ['okay', 'blink']);
        $this->placeLight('light.kitchen_strip', 'kitchen', ['colorloop']);
        $this->placeLight('light.kitchen_plain', 'kitchen', null);
        $this->placeLight('light.hallway_ceiling', 'hallway', ['okay']);

        $this->signal->emit($this->createFault(self::SMOKE_SENSOR), $this->fixture->ha);

        $call = $this->fixture->ha->getLastCall();
        self::assertSame(['light.turn_on'], $this->fixture->listCalledActions());
        self::assertSame(['effect' => 'okay'], $call->data);
        self::assertSame(['light.kitchen_all'], $call->listTargetedEntityIds());
    }

    public function testSkipsAreaWithoutSupportingLights(): void
    {
        $this->placeLight('light.kitchen_strip', 'kitchen', ['colorloop']);

        $this->signal->emit($this->createFault(self::SMOKE_SENSOR), $this->fixture->ha);

        self::assertTrue($this->fixture->ha->calls->isEmpty());
    }

    public function testSkipsFaultWithoutArea(): void
    {
        $this->placeLight('light.kitchen_all', 'kitchen', ['okay']);

        $this->signal->emit($this->createFault('binary_sensor.leak_garage'), $this->fixture->ha);

        self::assertTrue($this->fixture->ha->calls->isEmpty());
    }

    /** @param ?list<string> $effects */
    private function placeLight(string $entityId, string $areaId, ?array $effects): void
    {
        $this->fixture->placeInArea($entityId, $areaId, ucfirst($areaId));
        $this->fixture->ha->pushState($entityId, 'off', $effects === null ? [] : ['effect_list' => $effects]);
    }

    private function createFault(string $entityId): Fault
    {
        return new Fault(ReadingSource::forEntity(new EntityId($entityId)), $entityId, "{$entityId} is on");
    }
}
