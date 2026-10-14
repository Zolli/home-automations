<?php

declare(strict_types=1);

namespace Shared\HouseMode\Collection;

use Shared\HouseMode\WatchedTransition;
use Stewart\Contracts\Collection\ListCollection;

/** @extends ListCollection<WatchedTransition> */
final readonly class WatchedTransitionCollection extends ListCollection
{
    /** @param iterable<WatchedTransition> $transitions */
    public static function fromTransitions(iterable $transitions): self
    {
        return self::fromList($transitions);
    }

    public function stopWatchingAll(): void
    {
        foreach ($this as $transition) {
            $transition->stopWatching();
        }
    }
}
