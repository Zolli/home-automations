<?php

namespace App\TimedLight;

use App\Generated\Entities;
use App\TimedLight\Component\Config\TimedLightConfig;
use App\TimedLight\Component\TimedLightComponent;
use Psr\Log\LoggerInterface;
use Stewart\Contracts\App;
use Stewart\Contracts\Automation;
use Stewart\Contracts\HaContext;
use Stewart\Contracts\Schedule\Scheduler;
use Stewart\Contracts\Selector\Selector;

#[Automation(id: 'timed-light')]
final class TimedLightApp implements App
{
    /** @var list<TimedLightConfig> */
    private readonly array $configuration;

    /** @var array<string, TimedLightComponent> */
    private array $timedLightComponentsByName = [];

    /**
     * @param list<array<string, mixed>> $configuration
     */
    public function __construct(
        private readonly HaContext $context,
        private readonly Entities $entities,
        private readonly LoggerInterface $logger,
        private readonly Scheduler $scheduler,
        array $configuration,
    ) {
        $this->configuration = array_map(TimedLightConfig::fromConfig(...), $configuration);
        $this->logger->info("[TIMED-LIGHT] Created configuration for " . count($this->configuration) . ' instances');
    }

    public function initialize(): void
    {
        foreach ($this->configuration as $config) {
            $this->logger->info("[TIMED-LIGHT] Initializing for: " . $config->name);

            $timeLightComponent = new TimedLightComponent(
                $this->scheduler,
                $this->entities->light->getEntity($config->lightEntityId),
                $config->timeoutSeconds,
                ($config->coverEntityId !== null) ? $this->entities->cover->getEntity($config->coverEntityId) : null,
            );

            if (!empty($config->entrySensors)) {
                $timeLightComponent->attachToEntrySensorStream(
                    $this->context->watchStateChanges(Selector::anyOf(...$config->entrySensors))
                );
            }

            if(!empty($config->motionSensors)) {
                $timeLightComponent->attachToMotionSensorStream(
                    $this->context->watchStateChanges(Selector::anyOf(...$config->motionSensors))
                );
            }

            $this->timedLightComponentsByName[$config->name] = $timeLightComponent;
            $this->logger->info("[TIMED-LIGHT] Initialization done for: " . $config->name);
        }
    }

    public function dispose(): void
    {
        foreach ($this->timedLightComponentsByName as $component) {
            $component->dispose();
        }
    }
}