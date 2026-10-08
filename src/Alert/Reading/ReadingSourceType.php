<?php

declare(strict_types=1);

namespace Shared\Alert\Reading;

enum ReadingSourceType: string
{
    case Entity = 'entity';
    case MqttTopic = 'topic';
}
