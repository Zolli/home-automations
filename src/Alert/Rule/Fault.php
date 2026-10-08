<?php

declare(strict_types=1);

namespace Shared\Alert\Rule;

use Shared\Alert\Reading\Reading;
use Shared\Alert\Reading\ReadingSource;
use Shared\Template\Collection\TemplateVariableCollection;
use Shared\Template\TemplateVariable;
use Stewart\Contracts\Registry\Area;

final readonly class Fault
{
    public function __construct(
        public ReadingSource $source,
        public string $label,
        public string $reason,
    ) {}

    public static function fromReading(Reading $reading, string $reason): self
    {
        return new self($reading->source, $reading->label, $reason);
    }

    public function listTemplateVariables(?Area $area): TemplateVariableCollection
    {
        return TemplateVariableCollection::keyedByName([
            new TemplateVariable('reason', $this->reason),
            new TemplateVariable('label', $this->label),
            new TemplateVariable('area', $area->name ?? $this->label),
        ]);
    }
}
