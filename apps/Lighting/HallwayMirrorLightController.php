<?php

namespace App\Lighting;

use App\Generated\Entities;
use Psr\Log\LoggerInterface;
use Stewart\Contracts\App;
use Stewart\Contracts\Automation;
use Stewart\Contracts\History\HistoryQuery;
use Stewart\Contracts\State\StateChange;
use Stewart\Contracts\Time\Duration;

#[Automation(id: 'hallway-mirror-light-controller')]
class HallwayMirrorLightController implements App
{
    public function __construct(
        private readonly Entities $entities,
        private readonly LoggerInterface $logger,
    ) {}


    public function initialize(): void
    {
        $this->logger->info('HELLO from ' . self::class);

        $history = $this->entities->light->getEntity('light.master_bathroom_mirror_led_ch1')->getEntity()->getHistory(
            HistoryQuery::lastFor(Duration::hours(10))
        );

        $this->logger->info(sprintf(
            "There was %d state changes in %d seconds",
            $history->count(),
            $history->window->getDuration()->toSeconds()
        ));
        foreach ($history as $item) {
            $this->logger->info('State: ' . $item->state);
        }

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
        $this->logger->info(sprintf('EventType: %s', $eventType));

        $ceilingLight = $this->entities->light->getEntity('light.light_hallway_ceiling_03');

        switch ($eventType) {
            case 'on':
                $this->logger->info('case on');

                if ($ceilingLight->getState()?->isOff()) {
                    $this->logger->info('turning on');

                    $ceilingLight->turnOn(brightnessPct: 100.0);
                }

                break;
            case 'brightness_move_up':
                $this->logger->info('case brightness_move_up');

                $ceilingLight->turnOff();

                break;
        }
    }

    public function dispose(): void
    {
    }
}