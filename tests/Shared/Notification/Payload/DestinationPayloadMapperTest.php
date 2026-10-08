<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification\Payload;

use App\Tests\ServiceContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Notification\DestinationType;
use Shared\Notification\Exception\InvalidNotificationPayload;
use Shared\Notification\Payload\DestinationPayloadMapper;

final class DestinationPayloadMapperTest extends TestCase
{
    private DestinationPayloadMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new ServiceContainer(DestinationPayloadMapper::class)->getService(DestinationPayloadMapper::class);
    }

    public function testMapsDestinationsWithConditions(): void
    {
        $destinations = $this->mapper->mapToDestinations([
            ['type' => 'tts', 'target' => ['media_player.kitchen', 'media_player.hall']],
            ['type' => 'notify_service', 'target' => 'notify.phone', 'conditions' => [['type' => 'importance', 'field' => 'input_select.level']]],
        ], ['steps', 0, 'destinations']);

        $mapped = $destinations->listValues();

        self::assertCount(2, $mapped);
        self::assertSame(DestinationType::Tts, $mapped[0]->type);
        self::assertSame(['media_player.kitchen', 'media_player.hall'], $mapped[0]->targets);
        self::assertSame('importance >= input_select.level', $mapped[1]->condition->describe());
    }

    /** @return iterable<string, array{mixed, string}> */
    public static function invalidDestinations(): iterable
    {
        yield 'not a list' => ['notify.phone', 'steps[0].destinations must be of type array'];
        yield 'empty list' => [[], 'steps[0].destinations must have at least 1 items'];
        yield 'missing target' => [[['type' => 'tts']], 'steps[0].destinations[0].target is required'];
        yield 'unknown type' => [[['type' => 'telegram', 'target' => 'chat']], 'steps[0].destinations[0].type Destination type "telegram" is unknown'];
    }

    #[DataProvider('invalidDestinations')]
    public function testRejectsInvalidDestinationsWithPath(mixed $destinations, string $reason): void
    {
        $this->expectException(InvalidNotificationPayload::class);
        $this->expectExceptionMessage($reason);

        $this->mapper->mapToDestinations($destinations, ['steps', 0, 'destinations']);
    }
}
