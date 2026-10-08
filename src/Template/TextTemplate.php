<?php

declare(strict_types=1);

namespace Shared\Template;

use Shared\Template\Collection\TemplateVariableCollection;

final readonly class TextTemplate
{
    public function __construct(public string $text) {}

    public function render(TemplateVariableCollection $variables): string
    {
        return strtr($this->text, $variables->mapToPlaceholderReplacements());
    }
}
