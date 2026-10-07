<?php

declare(strict_types=1);

namespace App;

use Psr\Log\LoggerInterface;
use Shared\Notification\Importance;
use Shared\Notification\NotificationBuilder;
use Shared\Notification\Notifier;
use Shared\Notification\Sender\NotifyServiceSender;
use Stewart\Contracts\App;
use Stewart\Contracts\Automation;
use Stewart\Contracts\Event\HaEvent;
use Stewart\Contracts\HaContext;

#[Automation(id: 'battery-notes-battery-low-notifier')]
final class BatteryNotesBatteryLowNotifierApp implements App
{
    public function __construct(
        private readonly HaContext $context,
        private readonly Notifier $notifier,
        private readonly LoggerInterface $logger,
    ) {}

    public function initialize(): void
    {
        $this
            ->context
            ->watchEvents('battery_notes_battery_threshold')
            ->subscribe($this->whenBatteryNotesEventFired(...));
    }

    private function whenBatteryNotesEventFired(HaEvent $event): void
    {
        $isLow = $event->getValue('battery_low');

        $this->logger->info('battery_low type', ['value' => $isLow, 'type' => gettype($isLow)]);

        if ($isLow) {
            $body = sprintf('One of the device are running low on battery, its time to replace it: %s. '
                . 'You will need %sx%s battery to replace it.',
                $event->getValue('device_name'),
                $event->getValue('battery_quantity'),
                $event->getValue('battery_type')
            );

            $this->notifier->send(
                NotificationBuilder::create()
                    ->withTitle('Battery alert')
                    ->withBody($body)
                    ->withImportance(Importance::High)
                    ->withAdditionalData('visibility', 'public')
                    ->withAdditionalData('notification_icon', 'mdi:battery-arrow-down-outline')
                    ->withAdditionalData('color', '#D32F2F')
                    ->withAppendedDestinations(
                        NotifyServiceSender::createDestination('notify.mobile_app_zoli_phone')
                    )
                    ->withDebug()
                    ->buildNotification()
            );
        }
    }

    public function dispose(): void
    {
    }
}