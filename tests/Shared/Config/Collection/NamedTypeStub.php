<?php

declare(strict_types=1);

namespace App\Tests\Shared\Config\Collection;

use Shared\Config\NamedType;

final readonly class NamedTypeStub implements NamedType
{
    public function __construct(
        private string $name,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }
}
