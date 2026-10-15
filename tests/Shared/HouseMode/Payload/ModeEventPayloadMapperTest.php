<?php

declare(strict_types=1);

namespace App\Tests\Shared\HouseMode\Payload;

use PHPUnit\Framework\TestCase;
use Shared\HouseMode\Payload\ModeEventPayloadMapper;

final class ModeEventPayloadMapperTest extends TestCase
{
    public function testMapsEventCauseAndProducer(): void
    {
        self::assertSame(
            ['event' => 'night', 'data' => ['cause' => 'sun_below_horizon'], 'meta' => ['producer' => 'stewart/test']],
            new ModeEventPayloadMapper()->mapToPayload('night', 'sun_below_horizon', 'stewart/test'),
        );
    }
}
