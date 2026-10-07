<?php

declare(strict_types=1);

namespace App\Tests\Shared\Notification\Condition;

use App\Tests\Shared\Condition\StateStub;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Condition\Collection\ConditionTypeCollection;
use Shared\Condition\ConditionFactory;
use Shared\Condition\InvalidCondition;
use Shared\Notification\Condition\ImportanceCondition;
use Shared\Notification\Condition\ImportanceConditionType;
use Shared\Notification\Importance;
use Stewart\Contracts\Entity\EntityId;

final class ImportanceConditionTest extends TestCase
{
    /** @return iterable<string, array{Importance, string, bool}> */
    public static function levels(): iterable
    {
        yield 'low below normal' => [Importance::Low, 'normal', false];
        yield 'normal at normal' => [Importance::Normal, 'normal', true];
        yield 'critical above high' => [Importance::Critical, 'high', true];
        yield 'high below critical' => [Importance::High, 'critical', false];
        yield 'level is case-insensitive' => [Importance::High, 'High', true];
        yield 'unknown level sends' => [Importance::Low, 'mute', true];
        yield 'unavailable helper sends' => [Importance::Low, 'unavailable', true];
    }

    #[DataProvider('levels')]
    public function testComparesImportanceWithLevel(Importance $importance, string $level, bool $satisfied): void
    {
        $context = StateStub::context(
            $this,
            ['input_select.zoli_notification_level' => [$level]],
            [$importance],
        );

        self::assertSame($satisfied, new ImportanceCondition(new EntityId('input_select.zoli_notification_level'))->isSatisfied($context));
    }

    public function testMissingHelperSends(): void
    {
        $context = StateStub::context($this, [], [Importance::Low]);

        self::assertTrue(new ImportanceCondition(new EntityId('input_select.missing'))->isSatisfied($context));
    }

    public function testDefaultsToNormalImportanceWithoutFact(): void
    {
        $context = StateStub::context($this, ['input_select.level' => ['high']]);

        self::assertFalse(new ImportanceCondition(new EntityId('input_select.level'))->isSatisfied($context));
    }

    public function testIsBuiltByConditionFactory(): void
    {
        $factory = new ConditionFactory(ConditionTypeCollection::keyedByName([new ImportanceConditionType()]));

        self::assertEquals(
            new ImportanceCondition(new EntityId('input_select.zoli_notification_level')),
            $factory->mapToCondition(['type' => 'importance', 'field' => 'input_select.zoli_notification_level']),
        );
    }

    public function testFactoryRejectsMissingField(): void
    {
        $this->expectException(InvalidCondition::class);

        new ConditionFactory(ConditionTypeCollection::keyedByName([new ImportanceConditionType()]))->mapToCondition(['type' => 'importance']);
    }
}
