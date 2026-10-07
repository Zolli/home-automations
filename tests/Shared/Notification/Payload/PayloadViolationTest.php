<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification\Payload;

use PHPUnit\Framework\TestCase;
use Shared\Notification\Payload\PayloadViolation;

final class PayloadViolationTest extends TestCase
{
    public function testJoinsSegmentsIntoDottedPath(): void
    {
        $violation = PayloadViolation::atPath(['destinations', 0, 'conditions', 1, 'type'], 'is required');

        self::assertSame('destinations[0].conditions[1].type', $violation->path);
    }

    public function testNamesRootWithoutSegments(): void
    {
        self::assertSame('payload must be of type object', PayloadViolation::atPath([], 'must be of type object')->toString());
    }
}
