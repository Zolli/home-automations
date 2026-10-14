<?php

declare(strict_types=1);

namespace Shared\Config\Collection;

use LogicException;
use Shared\Config\NamedType;

final class InvalidNamedTypeCollection extends LogicException
{
    public static function forDuplicateName(NamedType $type): self
    {
        return new self(\sprintf('Type "%s" of %s is registered more than once.', $type->getName(), $type::class));
    }
}
