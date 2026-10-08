<?php

declare(strict_types=1);

namespace Shared\Alert\Mitigation\Collection;

use Shared\Alert\Mitigation\Mitigation;
use Stewart\Contracts\Collection\ListCollection;

/** @extends ListCollection<Mitigation> */
final readonly class MitigationCollection extends ListCollection
{
    /** @param iterable<Mitigation> $mitigations */
    public static function fromMitigations(iterable $mitigations): self
    {
        return self::fromList($mitigations);
    }
}
