<?php

declare(strict_types=1);

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Shared\Condition\Collection\ConditionTypeCollection;
use Shared\Condition\CompareConditionType;
use Shared\Condition\ConditionFactory;
use Shared\Condition\ConditionType;
use Shared\Condition\Operator;
use Shared\Notification\Action\ActionRouter;
use Shared\Notification\Condition\ImportanceConditionType;
use Shared\Notification\NotificationDispatcher;
use Shared\Notification\Notifier;
use Shared\Notification\Payload\NotificationActionPayloadMapper;
use Shared\Notification\Payload\NotificationPayloadMapper;
use Shared\Notification\Payload\NotificationPayloadSchema;
use Shared\Notification\Sender\ChimeTtsSender;
use Shared\Notification\Sender\Collection\DestinationSenderCollection;
use Shared\Notification\Sender\DestinationSender;
use Shared\Notification\Sender\NotifyServiceSender;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

// Register your own services here; apps receive them by type in their constructor.
return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->defaults()->autowire();

    $services->instanceof(ConditionType::class)->tag('shared.condition_type');
    $services->instanceof(DestinationSender::class)->tag('shared.notification_sender');

    foreach (Operator::cases() as $operator) {
        $services->set("shared.condition_type.{$operator->value}", CompareConditionType::class)->arg('$operator', $operator);
    }
    $services->set(ImportanceConditionType::class);
    $services->set(ConditionTypeCollection::class)
        ->factory([ConditionTypeCollection::class, 'keyedByName'])
        ->arg('$types', tagged_iterator('shared.condition_type'));
    $services->set(ConditionFactory::class);

    $services->set(NotifyServiceSender::class);
    $services->set(ChimeTtsSender::class);
    $services->set(ActionRouter::class);
    $services->set(DestinationSenderCollection::class)
        ->factory([DestinationSenderCollection::class, 'keyedByDestinationType'])
        ->arg('$senders', tagged_iterator('shared.notification_sender'));
    $services->set(NotificationDispatcher::class);
    $services->alias(Notifier::class, NotificationDispatcher::class);
    $services->set(Validator::class)
        ->arg('$loader', null)
        ->arg('$max_errors', 20)
        ->arg('$stop_at_first_error', false);
    $services->set(ErrorFormatter::class);
    $services->set(NotificationPayloadSchema::class);
    $services->set(NotificationPayloadMapper::class);
    $services->set(NotificationActionPayloadMapper::class);
};
