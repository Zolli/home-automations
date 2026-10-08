<?php

namespace App\Lighting;

use App\Generated\Entities;
use Stewart\Contracts\App;
use Stewart\Contracts\Automation;
use Stewart\Contracts\State\StateChange;

#[Automation(id: 'hallway-mirror-light-controller')]
class HallwayMirrorLightController implements App
{
    public function __construct(
        private readonly Entities $entities,
    ) {}


    public function initialize(): void
    {
        $this
            ->entities
            ->event
            ->getEntity('event.single_button_hallway_mirror_action')
            ->watchStateChanges()
            ->subscribe($this->onButtonPress(...));
    }

    private function onButtonPress(StateChange $change): void
    {
        $eventType = $change->to?->getStringAttribute('event_type');
        $ceilingLight = $this->entities->light->getEntity('light.light_hallway_ceiling_03');

        switch ($eventType) {
            case 'on':
                if ($ceilingLight->getState()?->isOff()) {
                    $ceilingLight->turnOn(brightnessPct: 100.0);
                }

                break;
            case 'brightness_move_up':
                $ceilingLight->turnOff();

                break;
        }
    }

    public function dispose(): void
    {
    }
}