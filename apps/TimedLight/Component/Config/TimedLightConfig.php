<?php

namespace App\TimedLight\Component\Config;

final readonly class TimedLightConfig
{
    public function __construct(
        public string $name,
        public string $lightEntityId,
        public float $timeoutSeconds,
        public ?array $motionSensors = null,
        public ?array $entrySensors = null,
        public ?string $coverEntityId = null,
    ) {}

    public static function fromConfig(array $options): self
    {
        return new self(
            $options['name'],
            $options['lightEntityId'],
            $options['timeoutSeconds'],
            (!empty($options['motionSensors'])) ? $options['motionSensors'] : null,
            (!empty($options['entrySensors'])) ? $options['entrySensors'] : null,
            (!empty($options['coverEntityId'])) ? $options['coverEntityId'] : null
        );
    }
}