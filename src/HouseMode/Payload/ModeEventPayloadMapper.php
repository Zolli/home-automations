<?php

declare(strict_types=1);

namespace Shared\HouseMode\Payload;

use Shared\HouseMode\ModeEvent;

final readonly class ModeEventPayloadMapper
{
    /** @return array{event: string, data: array{cause: string}, meta: array{producer: string}} */
    public function mapToPayload(ModeEvent $event): array
    {
        return [
            'event' => $event->event,
            'data'  => ['cause' => $event->cause],
            'meta'  => ['producer' => $event->producer],
        ];
    }
}
