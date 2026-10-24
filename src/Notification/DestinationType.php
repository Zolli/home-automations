<?php

declare(strict_types=1);

namespace Shared\Notification;

enum DestinationType: string
{
    case NotifyAction = 'notify_service';
    case Tts          = 'tts';
    case PhoneTts     = 'phone_tts';

    public function isSpoken(): bool
    {
        return match ($this) {
            self::NotifyAction        => false,
            self::Tts, self::PhoneTts => true,
        };
    }
}
