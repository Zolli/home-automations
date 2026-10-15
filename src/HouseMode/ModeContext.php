<?php

declare(strict_types=1);

namespace Shared\HouseMode;

use Psr\Log\LoggerInterface;
use Stewart\Contracts\Exposure\DeviceInfo;
use Stewart\Contracts\Exposure\EntityExposure;
use Stewart\Contracts\HaContext;
use Stewart\Contracts\Schedule\Scheduler;
use Stewart\Contracts\Time\Clock;

final readonly class ModeContext
{
    public function __construct(
        public HaContext $ha,
        public Scheduler $scheduler,
        public Clock $clock,
        public EntityExposure $exposure,
        public DeviceInfo $device,
        public LoggerInterface $logger,
    ) {}
}
