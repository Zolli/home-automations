<?php

declare(strict_types=1);

namespace Shared\Alert\Mitigation;

use Stewart\Contracts\Entity\EntityId;
use Stewart\Contracts\HaContext;
use Stewart\Contracts\Service\ServiceTarget;

final readonly class TurnOffMitigation implements Mitigation
{
    private const string VALVE_DOMAIN = 'valve';

    public function __construct(private EntityId $entityId) {}

    public function mitigate(HaContext $ha): void
    {
        $target = ServiceTarget::forEntities($this->entityId);

        if ($this->isValve()) {
            $ha->callService(self::VALVE_DOMAIN, 'close_valve', target: $target);

            return;
        }

        $ha->callService('homeassistant', 'turn_off', target: $target);
    }

    public function describe(): string
    {
        return ($this->isValve() ? 'Closed ' : 'Turned off ') . $this->entityId->value;
    }

    private function isValve(): bool
    {
        return $this->entityId->domain === self::VALVE_DOMAIN;
    }
}
