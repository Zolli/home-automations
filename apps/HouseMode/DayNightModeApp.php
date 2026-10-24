<?php

declare(strict_types=1);

namespace App\HouseMode;

use App\Generated\Entities;
use App\Generated\InputSelectEntity;
use Psr\Log\LoggerInterface;
use Shared\HouseMode\InvalidModeConfig;
use Shared\HouseMode\ModeContext;
use Shared\HouseMode\ModeTransition;
use Shared\HouseMode\ModeTransitionMapper;
use Shared\HouseMode\Payload\ModeEventPayloadMapper;
use Stewart\Contracts\App;
use Stewart\Contracts\Automation;
use Stewart\Contracts\Disposable;
use Stewart\Contracts\Exception\ActionCallException;
use Stewart\Contracts\Exception\ExposureException;
use Stewart\Contracts\Exception\IdentifierException;
use Stewart\Contracts\Exception\MqttException;
use Stewart\Contracts\Exposure\DeviceInfo;
use Stewart\Contracts\Exposure\EntityExposure;
use Stewart\Contracts\HaContext;
use Stewart\Contracts\Mqtt\Mqtt;
use Stewart\Contracts\Schedule\Scheduler;
use Stewart\Contracts\Time\Clock;

#[Automation(id: 'day-night-mode')]
final class DayNightModeApp implements App, Disposable
{
    private const string PRODUCER = 'stewart/day-night-mode';
    private const string DAY      = 'day';
    private const string NIGHT    = 'night';

    private readonly ModeTransition $dayTransition;

    private readonly ModeTransition $nightTransition;

    private readonly InputSelectEntity $modeSelectEntity;

    private readonly ModeContext $context;

    /**
     * @param array<array-key, mixed> $day
     * @param array<array-key, mixed> $night
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
        array $day,
        array $night,
        string $modeSelect = 'input_select.house_day_night_mode',
        private readonly string $topic = 'home/event/day-night-mode',
    ) {
        $this->dayTransition    = $transitionMapper->mapToTransition(self::DAY, $day);
        $this->nightTransition  = $transitionMapper->mapToTransition(self::NIGHT, $night);
        $this->modeSelectEntity = $entities->inputSelect->getEntity($modeSelect);
        $this->context          = new ModeContext(
            $ha,
            $scheduler,
            $clock,
            $exposure,
            new DeviceInfo('day_night_mode', 'Day/night mode'),
            $logger,
        );
    }

    /** @throws ExposureException */
    public function initialize(): void
    {
        $this->dayTransition->startWatching($this->context, fn(string $cause) => $this->switchMode(self::DAY, $cause));
        $this->nightTransition->startWatching(
            $this->context,
            fn(string $cause) => $this->switchMode(self::NIGHT, $cause),
        );
    }

    public function dispose(): void
    {
        $this->dayTransition->stopWatching();
        $this->nightTransition->stopWatching();
    }

    private function switchMode(string $mode, string $cause): void
    {
        try {
            $this->modeSelectEntity->selectOption($mode);
            $this->mqtt->publish($this->topic, $this->eventMapper->mapToPayload($mode, $cause, self::PRODUCER));
        } catch (ActionCallException|MqttException $e) {
            $this->logger->error("[MODE] Switching to {$mode} failed", ['exception' => $e]);
        }
    }
}
