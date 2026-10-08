<?php

declare(strict_types=1);

namespace Shared\Alert\Collection;

use Shared\Alert\MonitoredAlert;
use Stewart\Contracts\Collection\ListCollection;

/** @extends ListCollection<MonitoredAlert> */
final readonly class MonitoredAlertCollection extends ListCollection
{
    /** @param iterable<MonitoredAlert> $alerts */
    public static function fromAlerts(iterable $alerts): self
    {
        return self::fromList($alerts);
    }

    public function stopMonitoringAll(): void
    {
        foreach ($this as $alert) {
            $alert->stopMonitoring();
        }
    }
}
