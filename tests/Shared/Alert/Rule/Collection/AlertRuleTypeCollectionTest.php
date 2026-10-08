<?php

declare(strict_types=1);

namespace App\Tests\Shared\Alert\Rule\Collection;

use PHPUnit\Framework\TestCase;
use Shared\Alert\Config\InvalidAlertConfig;
use Shared\Alert\Rule\Collection\AlertRuleTypeCollection;
use Shared\Alert\Rule\StateMatchRuleType;
use Shared\Alert\Rule\ThresholdDirection;
use Shared\Alert\Rule\ThresholdRuleType;

final class AlertRuleTypeCollectionTest extends TestCase
{
    public function testFindsTypeByName(): void
    {
        $types = AlertRuleTypeCollection::keyedByName([new StateMatchRuleType(), new ThresholdRuleType(ThresholdDirection::Above)]);

        self::assertInstanceOf(ThresholdRuleType::class, $types->find('above'));
        self::assertNull($types->find('below'));
        self::assertSame(['state', 'above'], $types->listNames());
    }

    public function testRejectsDuplicateTypeName(): void
    {
        $this->expectException(InvalidAlertConfig::class);

        AlertRuleTypeCollection::keyedByName([new StateMatchRuleType(), new StateMatchRuleType()]);
    }
}
