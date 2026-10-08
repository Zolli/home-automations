<?php

declare(strict_types=1);

namespace Shared\Alert\Escalation;

enum AcknowledgeMethod: string
{
    case Button = 'button';
    case Dismissal = 'dismissal';
}
