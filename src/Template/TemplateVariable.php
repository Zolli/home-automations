<?php

declare(strict_types=1);

namespace Shared\Template;

final readonly class TemplateVariable
{
    public function __construct(
        public string $name,
        public string $value,
    ) {}
}
