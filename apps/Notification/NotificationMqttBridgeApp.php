<?php

declare(strict_types=1);

namespace App\Notification;

use Psr\Log\LoggerInterface;
use Shared\Notification\Action\ActionListener;
use Shared\Notification\Action\NotificationAction;
use Shared\Notification\InvalidDestination;
use Shared\Notification\Notifier;
use Shared\Notification\Payload\InvalidNotificationPayload;
use Shared\Notification\Payload\NotificationActionPayloadMapper;
use Shared\Notification\Payload\NotificationPayloadMapper;
use Stewart\Contracts\App;
use Stewart\Contracts\Automation;
use Stewart\Contracts\Exception\MqttException;
use Stewart\Contracts\HaContext;
use Stewart\Contracts\Mqtt\Mqtt;
use Stewart\Contracts\Mqtt\MqttMessage;
use Stewart\Contracts\Subscription;

#[Automation(id: 'notification-mqtt-bridge')]
final class NotificationMqttBridgeApp implements App
{
    private ?Subscription $subscription = null;

    /** @var array<string, ActionListener> */
    private array $awaitingActions = [];

    public function __construct(
        private readonly Mqtt $mqtt,
        private readonly HaContext $ha,
        private readonly Notifier $notifier,
        private readonly NotificationPayloadMapper $notificationMapper,
        private readonly NotificationActionPayloadMapper $actionMapper,
        private readonly LoggerInterface $logger,
        private readonly string $topic = 'stewart/notify',
        private readonly string $actionTopic = 'stewart/notify/action',
    ) {}

    public function initialize(): void
    {
        $this->subscription = $this->mqtt->watchMessages($this->topic)->subscribe($this->whenMessageReceived(...));
        $this->logger->info("[NOTIFICATION-MQTT] Listening on {$this->topic}");
    }

    public function dispose(): void
    {
        $this->subscription?->unsubscribe();

        foreach ($this->awaitingActions as $listener) {
            $listener->stopListening();
        }

        $this->awaitingActions = [];
    }

    private function whenMessageReceived(MqttMessage $message): void
    {
        try {
            $request = $this->notificationMapper->mapToNotificationRequest($message->decodeJsonPayload());
            $sent = $this->notifier->send($request->notification);
        } catch (MqttException|InvalidNotificationPayload|InvalidDestination $e) {
            $this->logger->warning("[NOTIFICATION-MQTT] Dropped message: {$e->getMessage()}", [
                'topic' => $message->topic,
                'payload' => $message->payload,
            ]);

            return;
        }

        $notification = $request->notification;
        $this->awaitingActions = array_filter($this->awaitingActions, static fn(ActionListener $listener) => $listener->isListening());

        if (!$notification->message->hasButtons()) {
            return;
        }

        $listener = $sent->listenForActions($this->ha)->onAnyAction(
            fn(NotificationAction $action) => $this->publishAction($action, $request->replyTopic ?? $this->actionTopic),
        );

        if ($listener->isListening()) {
            $this->awaitingActions[$listener->notificationId->value] = $listener;
        }
    }

    private function publishAction(NotificationAction $action, string $topic): void
    {
        $this->mqtt->publish($topic, $this->actionMapper->mapToPayload($action));
    }
}
