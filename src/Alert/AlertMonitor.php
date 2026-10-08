<?php

declare(strict_types=1);

namespace Shared\Alert;

use Psr\Log\LoggerInterface;
use Shared\Notification\Notifier;

final readonly class AlertMonitor
{
    public function __construct(
        private Notifier $notifier,
        private FaultLocator $locator,
        private LoggerInterface $logger,
    ) {}

    public function startMonitoring(AlertDefinition $definition, MonitoringContext $context): MonitoredAlert
    {
        $alert = new MonitoredAlert($definition, $context, $this->notifier, $this->locator, $this->logger);
        $alert->startMonitoring();

        return $alert;
    }
}
