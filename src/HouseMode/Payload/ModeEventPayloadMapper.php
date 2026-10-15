<?php

declare(strict_types=1);

namespace Shared\HouseMode\Payload;

final readonly class ModeEventPayloadMapper
{
    /** @return array{event: string, data: array{cause: string}, meta: array{producer: string}} */
    public function mapToPayload(string $event, string $cause, string $producer): array
    {
        return [
            'event' => $event,
            'data'  => ['cause' => $cause],
            'meta'  => ['producer' => $producer],
        ];
    }
}
