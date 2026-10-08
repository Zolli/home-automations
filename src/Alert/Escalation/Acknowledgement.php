<?php

declare(strict_types=1);

namespace Shared\Alert\Escalation;

use Shared\Notification\Action\NotificationAction;
use Shared\Notification\Action\NotificationDismissal;

final readonly class Acknowledgement
{
    public function __construct(
        public AcknowledgeMethod $method,
        public ?string $userId = null,
    ) {}

    public static function fromAction(NotificationAction $action): self
    {
        return new self(AcknowledgeMethod::Button, $action->userId);
    }

    public static function fromDismissal(NotificationDismissal $dismissal): self
    {
        return new self(AcknowledgeMethod::Dismissal, $dismissal->userId);
    }
}
