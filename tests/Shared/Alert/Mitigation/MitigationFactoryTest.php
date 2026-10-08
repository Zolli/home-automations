<?php

declare(strict_types=1);

namespace App\Tests\Shared\Alert\Mitigation;

use App\Tests\ServiceContainer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Alert\Config\InvalidAlertConfig;
use Shared\Alert\Mitigation\Mitigation;
use Shared\Alert\Mitigation\MitigationFactory;

final class MitigationFactoryTest extends TestCase
{
    private MitigationFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ServiceContainer(MitigationFactory::class)->getService(MitigationFactory::class);
    }

    public function testMapsMitigationsInOrder(): void
    {
        $mitigations = $this->factory->mapToMitigations([
            ['type' => 'turn-off', 'entity' => 'valve.main_water'],
            ['type' => 'turn-off', 'entity' => 'switch.dishwasher'],
        ]);

        self::assertSame(
            ['Closed valve.main_water', 'Turned off switch.dishwasher'],
            $mitigations->mapToList(static fn(Mitigation $mitigation): string => $mitigation->describe()),
        );
    }

    /** @return iterable<string, array{array<array-key, mixed>}> */
    public static function invalidMitigations(): iterable
    {
        yield 'not an object' => [['valve.main_water']];
        yield 'unknown type' => [[['type' => 'siren', 'entity' => 'siren.hall']]];
        yield 'missing entity' => [[['type' => 'turn-off']]];
    }

    /** @param array<array-key, mixed> $mitigations */
    #[DataProvider('invalidMitigations')]
    public function testRejectsInvalidMitigation(array $mitigations): void
    {
        $this->expectException(InvalidAlertConfig::class);

        $this->factory->mapToMitigations($mitigations);
    }
}
