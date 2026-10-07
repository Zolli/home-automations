<?php

declare(strict_types=1);

namespace Shared\Notification;

final readonly class Meta
{
    public NotificationId $id;

    public function __construct(
        public Importance $importance = Importance::DEFAULT,
        public bool $debug = false,
        ?NotificationId $id = null,
    ) {
        $this->id = $id ?? NotificationId::generate();
    }
}
