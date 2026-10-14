<?php

declare(strict_types=1);

namespace App\Tests\Shared\Config\Collection;

use PHPUnit\Framework\TestCase;
use Shared\Config\Collection\InvalidNamedTypeCollection;
use Shared\Config\InvalidTypedOptions;
use Shared\Config\TypedOptions;

final class NamedTypeCollectionTest extends TestCase
{
    public function testFindsTypeByName(): void
    {
        $always = new NamedTypeStub('always');
        $types = NamedTypeStubCollection::keyedByName([new NamedTypeStub('never'), $always]);

        self::assertSame($always, $types->find('always'));
        self::assertNull($types->find('sometimes'));
    }

    public function testRejectsTwoTypesWithSameName(): void
    {
        $this->expectException(InvalidNamedTypeCollection::class);

        NamedTypeStubCollection::keyedByName([new NamedTypeStub('always'), new NamedTypeStub('always')]);
    }

    public function testRequiresTypeNamedByOptions(): void
    {
        $always = new NamedTypeStub('always');
        $types = NamedTypeStubCollection::keyedByName([$always]);

        self::assertSame($always, $types->requireType(TypedOptions::fromArray('Stub', ['type' => 'always'])));
    }

    public function testRejectsUnknownTypeNamedByOptions(): void
    {
        $this->expectException(InvalidTypedOptions::class);

        NamedTypeStubCollection::keyedByName([new NamedTypeStub('always')])->requireType(TypedOptions::fromArray('Stub', ['type' => 'never']));
    }
}
