<?php

declare(strict_types=1);

namespace App\HouseMode;

use App\Generated\Entities;
use App\Generated\InputBooleanEntity;
use Psr\Log\LoggerInterface;
use Shared\HouseMode\InvalidModeConfig;
use Shared\HouseMode\ModeContext;
use Shared\HouseMode\ModeTransition;
use Shared\HouseMode\ModeTransitionMapper;
use Shared\HouseMode\Payload\ModeEventPayloadMapper;
use Stewart\Contracts\App;
use Stewart\Contracts\Automation;
use Stewart\Contracts\Exception\ExposureException;
use Stewart\Contracts\Exception\IdentifierException;
use Stewart\Contracts\Exception\MqttException;
use Stewart\Contracts\Exception\ServiceCallException;
use Stewart\Contracts\Exposure\DeviceInfo;
use Stewart\Contracts\Exposure\EntityExposure;
use Stewart\Contracts\HaContext;
use Stewart\Contracts\Mqtt\Mqtt;
use Stewart\Contracts\Schedule\Scheduler;
use Stewart\Contracts\Time\Clock;

#[Automation(id: 'sleep-mode')]
final class SleepModeApp implements App
{
    private const string PRODUCER    = 'stewart/sleep-mode';
    private const string SLEEP_EVENT = 'sleep';
    private const string AWAKE_EVENT = 'awake';

    private readonly ModeTransition $activation;

    private readonly ModeTransition $deactivation;

    private readonly InputBooleanEntity $sleepSwitchEntity;

    private readonly ModeContext $context;

    /**
     * @param array<array-key, mixed> $activation
     * @param array<array-key, mixed> $deactivation
     * @throws InvalidModeConfig|IdentifierException|ExposureException
     */
    public function __construct(
        ModeTransitionMapper $transitionMapper,
        private readonly ModeEventPayloadMapper $eventMapper,
        Entities $entities,
        private readonly Mqtt $mqtt,
        HaContext $ha,
        Scheduler $scheduler,
        Clock $clock,
        EntityExposure $exposure,
        private readonly LoggerInterface $logger,
        array $activation,
        array $deactivation,
        string $sleepSwitch = 'input_boolean.sleep_mode',
        private readonly string $topic = 'home/event/sleep-mode',
    ) {
        $this->activation        = $transitionMapper->mapToTransition('activation', $activation);
        $this->deactivation      = $transitionMapper->mapToTransition('deactivation', $deactivation);
        $this->sleepSwitchEntity = $entities->inputBoolean->getEntity($sleepSwitch);
        $this->context           = new ModeContext(
            $ha,
            $scheduler,
            $clock,
            $exposure,
            new DeviceInfo('sleep_mode', 'Sleep mode'),
            $logger,
        );
    }

    /** @throws ExposureException */
    public function initialize(): void
    {
        $this->activation->startWatching($this->context, fn(string $cause) => $this->switchSleepMode(true, $cause));
        $this->deactivation->startWatching(
            $this->context,
            fn(string $cause) => $this->switchSleepMode(false, $cause),
        );
    }

    public function dispose(): void
    {
        $this->activation->stopWatching();
        $this->deactivation->stopWatching();
    }

    private function switchSleepMode(bool $asleep, string $cause): void
    {
        if (($this->sleepSwitchEntity->getState()?->isOn() ?? false) === $asleep) {
            return;
        }

        $event = $asleep ? self::SLEEP_EVENT : self::AWAKE_EVENT;

        try {
            if ($asleep) {
                $this->sleepSwitchEntity->turnOn();
            } else {
                $this->sleepSwitchEntity->turnOff();
            }

            $this->mqtt->publish($this->topic, $this->eventMapper->mapToPayload($event, $cause, self::PRODUCER));
        } catch (ServiceCallException|MqttException $e) {
            $this->logger->error("[MODE] Switching sleep mode to {$event} failed", ['exception' => $e]);
        }
    }
}
