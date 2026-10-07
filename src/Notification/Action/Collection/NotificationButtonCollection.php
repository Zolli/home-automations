<?php

declare(strict_types=1);

namespace Shared\Notification\Action\Collection;

use Shared\Notification\Action\NotificationButton;
use Stewart\Contracts\Collection\ListCollection;

/** @extends ListCollection<NotificationButton> */
final readonly class NotificationButtonCollection extends ListCollection
{
    /** @param iterable<NotificationButton> $buttons */
    public static function fromButtons(iterable $buttons): self
    {
        return self::fromList($buttons);
    }

    public function withAppendedButtons(NotificationButton ...$buttons): self
    {
        return self::fromButtons([...$this, ...$buttons]);
    }
}
