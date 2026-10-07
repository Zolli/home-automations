<?php

declare(strict_types=1);

namespace Shared\Notification\Action;

use Stewart\Contracts\HaContext;
use Stewart\Contracts\Schedule\ScheduledTask;

final readonly class ListeningNotification
{
    public function __construct(
        public ActionListener $listener,
        public HaContext $appContext,
        private ScheduledTask $expiry,
    ) {}

    public function isOwnedBy(HaContext $appContext): bool
    {
        return $this->appContext === $appContext;
    }

    public function cancelExpiry(): void
    {
        $this->expiry->cancel();
    }
}
