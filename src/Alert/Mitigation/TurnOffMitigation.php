<?php

declare(strict_types=1);

namespace Shared\Alert\Mitigation;

use Stewart\Contracts\Action\ActionTarget;
use Stewart\Contracts\Entity\EntityId;
use Stewart\Contracts\HaContext;

final readonly class TurnOffMitigation implements Mitigation
{
    private const string VALVE_DOMAIN = 'valve';

    public function __construct(
        private EntityId $entityId,
    ) {}

    public function mitigate(HaContext $ha): void
    {
        $target = ActionTarget::forEntities($this->entityId);

        if ($this->isValve()) {
            $ha->callAction('valve.close_valve', target: $target);

            return;
        }

        $ha->callAction('homeassistant.turn_off', target: $target);
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
