<?php

declare(strict_types=1);

namespace Shared\Alert;

enum AlertState: string
{
    case Idle = 'idle';
    case Raised = 'raised';
    case Acknowledged = 'acknowledged';
}
