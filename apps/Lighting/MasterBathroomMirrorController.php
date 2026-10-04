<?php

namespace App\Lighting;

use App\Generated\Entities;
use Psr\Log\LoggerInterface;
use Stewart\Contracts\App;
use Stewart\Contracts\Automation;
use Stewart\Contracts\Mqtt\Mqtt;
use Stewart\Contracts\Mqtt\MqttMessage;
use Stewart\Contracts\Schedule\Scheduler;
use Stewart\Contracts\State\StateChange;
use Stewart\Contracts\Time\Duration;

#[Automation(id: 'master-bathroom-mirror-controller')]
class MasterBathroomMirrorController implements App
{
    public function __construct(
        private readonly Entities $entities,
        private readonly Scheduler $scheduler,
        private readonly Mqtt $mqtt,
        private LoggerInterface $logger,
    ) {}


    public function initialize(): void
    {
        $this->mqtt->watchMessages('test/in/#')->subscribe(function (MqttMessage $message) {
            $this->logger->info('MESSAGE ' . $message->payload);
            $this->mqtt->publish('test/out/a', $message->payload);
        });

          $this
              ->entities
              ->event
              ->getEntity('event.single_button_master_bathroom_mirror_action')
              ->watchStateChanges()
              ->subscribe($this->onWallButtonPress(...));

          $this
              ->entities
              ->light
              ->getEntity('light.light_master_bathroom_ceiling')
              ->watchStateChanges()
              ->whenChangedTo('off')
                ->subscribe(
                  $this->whenCeilingLightTurnsOff(...)
              );
    }

    private function onWallButtonPress(StateChange $change): void
    {
        $eventType = $change->to->getStringAttribute('event_type');
        $mirrorLight = $this->entities->light->getEntity('light.master_bathroom_mirror_led_ch1');
        $mirrorLightAdaptiveBrightnessSwitch = $this
            ->entities
            ->switch
            ->getEntity('switch.adaptive_lighting_adapt_brightness_master_bathroom_mirror');

        switch ($eventType) {
            case 'on':
                if ($mirrorLight->getState()?->isOn()) {
                    $mirrorLight->turnOff();

                    break;
                }

                $mirrorLightAdaptiveBrightnessSwitch->turnOn();
                $mirrorLight->turnOn();

                break;
            case 'brightness_move_up':
                $mirrorLightAdaptiveBrightnessSwitch->turnOff();
                $mirrorLight->turnOn(brightnessPct: 100.0);

                break;
        }
    }

    private function whenCeilingLightTurnsOff(StateChange $change): void
    {
        $mirrorLight = $this->entities->light->getEntity('light.master_bathroom_mirror_led_ch1');

        $mirrorLight->turnOn(brightnessPct: 10.0);

        $this->scheduler->runAfter(Duration::seconds(15), static function () use ($mirrorLight) {
            $mirrorLight->turnOff();
        });
    }

    public function dispose(): void
    {
    }

}