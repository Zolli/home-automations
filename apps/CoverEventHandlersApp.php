<?php

namespace App;

use App\Generated\Entities;
use App\Generated\Services;
use Psr\Log\LoggerInterface;
use Stewart\Contracts\App;
use Stewart\Contracts\Automation;
use Stewart\Contracts\HaContext;
use Stewart\Contracts\Schedule\ScheduledTask;
use Stewart\Contracts\Schedule\Scheduler;
use Stewart\Contracts\State\StateChange;
use Stewart\Contracts\Time\Duration;

#[Automation(id: 'cover-event-handlers')]
class CoverEventHandlersApp implements App
{

    private ?ScheduledTask $lightTurnOnDelay = null;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly Entities $entities,
        private readonly Services $services,
        private readonly Scheduler $scheduler,
    ) {}

    public function initialize(): void
    {
        $this->logger->info('Initializing cover event handlers...');

        $this
            ->entities
            ->binarySensor
            ->getEntity('binary_sensor.covers_closed_kitchen_and_living_room')
            ->watchStateChanges()->whenChangedTo('on')->subscribe($this->whenKitchenAndLivingRoomClosed(...));

        $this->logger->info('Cover event handler registered');
    }

    private function whenKitchenAndLivingRoomClosed(StateChange $stateChange): void
    {
        $this->logger->info('State changed', ['from' => $stateChange->from, 'to' => $stateChange->to]);

        if ($this->lightTurnOnDelay?->isActive()) {
            $this->logger->info('Skipping state change because of active schedule');
            return;
        }

        $this->logger->info('State change acknowledged, waiting 20s before continuing...');

        $this->lightTurnOnDelay = $this->scheduler->runAfter(
            Duration::seconds(20),
            function (): void {
                $this->services->light->turnOn(
                    $this->entities->light->getEntity('light.light_kitchen_all'),
                    brightnessPct: 40
                );
            }
        );
    }

    public function dispose(): void
    {
        $this->lightTurnOnDelay?->cancel();
    }
}