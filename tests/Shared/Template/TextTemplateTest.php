<?php

declare(strict_types=1);

namespace App\Tests\Shared\Template;

use PHPUnit\Framework\TestCase;
use Shared\Template\Collection\TemplateVariableCollection;
use Shared\Template\TemplateVariable;
use Shared\Template\TextTemplate;

final class TextTemplateTest extends TestCase
{
    public function testReplacesEveryOccurrenceOfKnownVariables(): void
    {
        $variables = TemplateVariableCollection::keyedByName([new TemplateVariable('room', 'Kitchen'), new TemplateVariable('kind', 'Smoke')]);

        self::assertSame('Smoke in the Kitchen, Kitchen!', new TextTemplate('{kind} in the {room}, {room}!')->render($variables));
    }

    public function testKeepsUnknownPlaceholders(): void
    {
        self::assertSame('Leak in {room}', new TextTemplate('Leak in {room}')->render(TemplateVariableCollection::keyedByName([])));
    }

    public function testDoesNotReplaceInsideSubstitutedValues(): void
    {
        $variables = TemplateVariableCollection::keyedByName([new TemplateVariable('a', '{b}'), new TemplateVariable('b', 'x')]);

        self::assertSame('{b} x', new TextTemplate('{a} {b}')->render($variables));
    }
}
