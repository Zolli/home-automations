<?php

declare(strict_types=1);

namespace App\Tests\Shared\Alert\Rule;

use PHPUnit\Framework\TestCase;
use Shared\Alert\Reading\ReadingSource;
use Shared\Alert\Rule\Fault;
use Shared\Template\TextTemplate;
use Stewart\Contracts\Entity\EntityId;
use Stewart\Contracts\Registry\Area;
use Stewart\Contracts\Registry\AreaId;

final class FaultTest extends TestCase
{
    private const string TEMPLATE = '{reason} | {label} | {area}';

    private Fault $fault;

    protected function setUp(): void
    {
        $this->fault = new Fault(ReadingSource::forEntity(new EntityId('binary_sensor.leak_kitchen')), 'Kitchen leak', 'Kitchen leak is on');
    }

    public function testFillsReasonLabelAndArea(): void
    {
        $variables = $this->fault->listTemplateVariables(new Area(new AreaId('kitchen'), 'Kitchen'));

        self::assertSame('Kitchen leak is on | Kitchen leak | Kitchen', new TextTemplate(self::TEMPLATE)->render($variables));
    }

    public function testUsesLabelAsAreaWhenUnknown(): void
    {
        $variables = $this->fault->listTemplateVariables(null);

        self::assertSame('Kitchen leak is on | Kitchen leak | Kitchen leak', new TextTemplate(self::TEMPLATE)->render($variables));
    }
}
