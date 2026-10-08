<?php

declare(strict_types=1);

namespace Shared\Alert;

use Stewart\Contracts\HaContext;
use Stewart\Contracts\Mqtt\Mqtt;
use Stewart\Contracts\Schedule\Scheduler;
use Stewart\Contracts\Time\Clock;

final readonly class MonitoringContext
{
    public function __construct(
        public HaContext $ha,
        public Scheduler $scheduler,
        public Mqtt $mqtt,
        public Clock $clock,
    ) {}
}
