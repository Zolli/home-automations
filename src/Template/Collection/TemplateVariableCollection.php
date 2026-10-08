<?php

declare(strict_types=1);

namespace Shared\Template\Collection;

use Shared\Template\TemplateVariable;
use Stewart\Contracts\Collection\KeyedCollection;

/** @extends KeyedCollection<string, TemplateVariable> */
final readonly class TemplateVariableCollection extends KeyedCollection
{
    /** @param iterable<TemplateVariable> $variables */
    public static function keyedByName(iterable $variables): self
    {
        return self::keyedBy($variables, static fn(TemplateVariable $variable): string => $variable->name);
    }

    /** @return array<string, string> */
    public function mapToPlaceholderReplacements(): array
    {
        $replacements = [];

        foreach ($this as $variable) {
            $replacements['{' . $variable->name . '}'] = $variable->value;
        }

        return $replacements;
    }
}
