<?php

declare(strict_types=1);

namespace App\Tests\Shared\Alert\Config;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Alert\Config\ReadingSourceMapper;
use Shared\Alert\Reading\ReadingSource;
use Shared\Config\InvalidTypedOptions;
use Shared\Config\TypedOptions;
use Stewart\Contracts\Entity\EntityId;

final class ReadingSourceMapperTest extends TestCase
{
    public function testCombinesEntityAndTopicSources(): void
    {
        $sources = new ReadingSourceMapper()->mapToSources($this->createOptions([
            'entity'   => 'sensor.a',
            'entities' => ['sensor.b'],
            'topic'    => 'meter/l1',
            'topics'   => ['meter/l2'],
        ]));

        self::assertEquals(
            [
                ReadingSource::forEntity(new EntityId('sensor.a')),
                ReadingSource::forEntity(new EntityId('sensor.b')),
                ReadingSource::forTopic('meter/l1'),
                ReadingSource::forTopic('meter/l2'),
            ],
            $sources->listValues(),
        );
    }

    public function testMapsTopicSources(): void
    {
        $sources = new ReadingSourceMapper()->mapToTopicSources($this->createOptions(['topics' => ['meter/l1']]));

        self::assertEquals([ReadingSource::forTopic('meter/l1')], $sources->listValues());
    }

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function invalidSources(): iterable
    {
        yield 'no sources' => [[]];
        yield 'empty lists' => [['entities' => [], 'topics' => []]];
        yield 'invalid entity id' => [['entities' => ['kitchen']]];
        yield 'wildcard topic' => [['topic' => 'meter/#']];
        yield 'non-string topic' => [['topics' => [5]]];
    }

    /** @param array<string, mixed> $fields */
    #[DataProvider('invalidSources')]
    public function testRejectsInvalidSources(array $fields): void
    {
        $this->expectException(InvalidTypedOptions::class);

        new ReadingSourceMapper()->mapToSources($this->createOptions($fields));
    }

    public function testRejectsEntitiesWhereTopicsAreRequired(): void
    {
        $this->expectException(InvalidTypedOptions::class);

        new ReadingSourceMapper()->mapToTopicSources($this->createOptions(['entities' => ['sensor.a']]));
    }

    /** @param array<string, mixed> $fields */
    private function createOptions(array $fields): TypedOptions
    {
        return TypedOptions::fromArray('Alert rule', ['type' => 'state', ...$fields]);
    }
}
