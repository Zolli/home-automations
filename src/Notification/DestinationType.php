<?php

declare(strict_types=1);

namespace Shared\Notification;

enum DestinationType: string
{
    case NotifyService = 'notify_service';
    case Tts = 'tts';
}
