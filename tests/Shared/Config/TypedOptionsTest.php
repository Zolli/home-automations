<?php

declare(strict_types=1);

namespace App\Tests\Shared\Config;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Config\InvalidTypedOptions;
use Shared\Config\TypedOptions;
use Stewart\Contracts\Entity\EntityId;

final class TypedOptionsTest extends TestCase
{
    /** @return iterable<string, array{array<array-key, mixed>}> */
    public static function missingTypes(): iterable
    {
        yield 'absent' => [['field' => 'sun.sun']];
        yield 'empty' => [['type' => '']];
        yield 'not a string' => [['type' => 5]];
    }

    /** @param array<array-key, mixed> $fields */
    #[DataProvider('missingTypes')]
    public function testRejectsMissingType(array $fields): void
    {
        $this->expectException(InvalidTypedOptions::class);

        TypedOptions::fromArray('Stub', $fields);
    }

    public function testReturnsPresentValueEvenWhenNull(): void
    {
        self::assertNull($this->createOptions(['value' => null])->requireValue('value'));
    }

    public function testRejectsAbsentRequiredValue(): void
    {
        $this->expectException(InvalidTypedOptions::class);

        $this->createOptions([])->requireValue('value');
    }

    public function testReadsDurationFromSeconds(): void
    {
        self::assertSame(1_500, $this->createOptions(['forSeconds' => 1.5])->findDuration('forSeconds')->toMilliseconds());
        self::assertSame(0, $this->createOptions([])->findDuration('forSeconds')->toMilliseconds());
    }

    public function testRejectsNegativeDuration(): void
    {
        $this->expectException(InvalidTypedOptions::class);

        $this->createOptions(['forSeconds' => -1])->findDuration('forSeconds');
    }

    public function testReadsEntityIdList(): void
    {
        self::assertEquals(
            [new EntityId('sensor.a'), new EntityId('sensor.b')],
            $this->createOptions(['entities' => ['sensor.a', 'sensor.b']])->findEntityIds('entities'),
        );
    }

    public function testRejectsInvalidEntityIdInList(): void
    {
        $this->expectException(InvalidTypedOptions::class);

        $this->createOptions(['entities' => ['sensor.a', 'kitchen']])->findEntityIds('entities');
    }

    /** @param array<array-key, mixed> $fields */
    private function createOptions(array $fields): TypedOptions
    {
        return TypedOptions::fromArray('Stub', ['type' => 'stub', ...$fields]);
    }
}
