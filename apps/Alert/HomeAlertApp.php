<?php

declare(strict_types=1);

namespace App\Alert;

use Psr\Log\LoggerInterface;
use Shared\Alert\AlertDefinition;
use Shared\Alert\AlertMonitor;
use Shared\Alert\Collection\AlertDefinitionCollection;
use Shared\Alert\Collection\MonitoredAlertCollection;
use Shared\Alert\Config\AlertConfigMapper;
use Shared\Alert\Config\InvalidAlertConfig;
use Shared\Alert\MonitoringContext;
use Stewart\Contracts\App;
use Stewart\Contracts\Automation;
use Stewart\Contracts\HaContext;
use Stewart\Contracts\Mqtt\Mqtt;
use Stewart\Contracts\Schedule\Scheduler;
use Stewart\Contracts\Time\Clock;

#[Automation(id: 'home-alert')]
final class HomeAlertApp implements App
{
    private readonly AlertDefinitionCollection $definitions;

    private readonly MonitoringContext $context;

    private MonitoredAlertCollection $monitoredAlerts;

    /**
     * @param array<array-key, mixed> $policies
     * @param array<array-key, mixed> $alerts
     * @throws InvalidAlertConfig
     */
    public function __construct(
        AlertConfigMapper $configMapper,
        private readonly AlertMonitor $monitor,
        HaContext $ha,
        Scheduler $scheduler,
        Mqtt $mqtt,
        Clock $clock,
        private readonly LoggerInterface $logger,
        array $policies = [],
        array $alerts = [],
    ) {
        $this->definitions = $configMapper->mapToDefinitions($policies, $alerts);
        $this->context = new MonitoringContext($ha, $scheduler, $mqtt, $clock);
        $this->monitoredAlerts = MonitoredAlertCollection::empty();
    }

    public function initialize(): void
    {
        $this->monitoredAlerts = MonitoredAlertCollection::fromAlerts(
            $this->definitions->mapToList(fn(AlertDefinition $definition) => $this->monitor->startMonitoring($definition, $this->context)),
        );

        foreach ($this->definitions as $definition) {
            $this->logger->info("[ALERT] Monitoring {$definition->id}: {$definition->rule->describe()}");
        }
    }

    public function dispose(): void
    {
        $this->monitoredAlerts->stopMonitoringAll();
        $this->monitoredAlerts = MonitoredAlertCollection::empty();
    }
}
