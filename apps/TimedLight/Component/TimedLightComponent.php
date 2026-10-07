<?php

namespace App\TimedLight\Component;

use App\Generated\CoverEntity;
use App\Generated\LightEntity;
use Stewart\Contracts\Schedule\ScheduledTask;
use Stewart\Contracts\Schedule\Scheduler;
use Stewart\Contracts\State\StateChange;
use Stewart\Contracts\StateChangeStream;
use Stewart\Contracts\Time\Duration;

class TimedLightComponent
{
    private ?ScheduledTask $timeoutTask = null;

    public function __construct(
        private readonly Scheduler $scheduler,
        private readonly LightEntity $lightEntity,
        private readonly float $lightTimeoutSeconds,
        private readonly ?CoverEntity $coverEntity = null,
    ) {}

    public function attachToEntrySensorStream(StateChangeStream $stateChangeStream): void
    {
        $stateChangeStream->distinctUntilChanged()->subscribe($this->whenSensorsChanges(...));
    }

    public function attachToMotionSensorStream(StateChangeStream $stateChangeStream): void
    {
        $stateChangeStream->distinctUntilChanged()->subscribe($this->whenSensorsChanges(...));
    }

    public function dispose(): void
    {
        $this->timeoutTask?->cancel();
    }

    private function whenSensorsChanges(StateChange $stateChange): void
    {
        switch ($stateChange->to?->state) {
            case 'on':
                if (
                    $this->coverEntity !== null
                    && $this->coverEntity->getState()?->value === 'opened'
                ) {
                    break;
                }

                $this->turnOnLight();

                $this->timeoutTask = $this->scheduler->runAfter(
                    Duration::seconds($this->lightTimeoutSeconds),
                    function() {
                        $this->turnOffLight();
                    }
                );

                break;
            case 'off':
                if ($stateChange->to?->getStringAttribute('device_class') === 'door') {
                    $this->turnOffLight();

                    break;
                }

                $this->timeoutTask?->cancel();
                $this->timeoutTask = $this->scheduler->runAfter(Duration::seconds($this->lightTimeoutSeconds), function() {
                    $this->turnOffLight();
                });

                break;
        }
    }

    private function turnOnLight(): void
    {
        if ($this->timeoutTask?->isActive()) {
            $this->timeoutTask->cancel();
        }

        $this->lightEntity->turnOn();
    }

    private function turnOffLight(): void
    {
        $this->lightEntity->turnOff();
    }
}