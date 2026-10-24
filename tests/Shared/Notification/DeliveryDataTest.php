<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification;

use PHPUnit\Framework\TestCase;
use Shared\Notification\DeliveryData;

final class DeliveryDataTest extends TestCase
{
    public function testMergesNestedMaps(): void
    {
        self::assertSame(
            ['data' => ['color' => 'red', 'tag' => 'hello'], 'title' => 'Hi'],
            DeliveryData::mergeReplacingLists(
                ['data' => ['color' => 'red'], 'title' => 'Hi'],
                ['data' => ['tag' => 'hello']],
            ),
        );
    }

    public function testReplacesListsInsteadOfMergingItems(): void
    {
        self::assertSame(
            ['target' => ['device_3']],
            DeliveryData::mergeReplacingLists(['target' => ['device_1', 'device_2']], ['target' => ['device_3']]),
        );
    }

    public function testLaterOverrideWins(): void
    {
        self::assertSame(
            ['data' => ['color' => 'blue']],
            DeliveryData::mergeReplacingLists(
                ['data' => ['color' => 'red']],
                ['data' => ['color' => 'green']],
                ['data' => ['color' => 'blue']],
            ),
        );
    }
}
