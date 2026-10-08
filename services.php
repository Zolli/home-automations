<?php

declare(strict_types=1);

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Shared\Alert\AlertMonitor;
use Shared\Alert\FaultLocator;
use Shared\Alert\Config\AlertConfigMapper;
use Shared\Alert\Escalation\EscalationPolicyMapper;
use Shared\Alert\Mitigation\Collection\MitigationTypeCollection;
use Shared\Alert\Mitigation\MitigationFactory;
use Shared\Alert\Mitigation\MitigationType;
use Shared\Alert\Mitigation\TurnOffMitigationType;
use Shared\Alert\Rule\AlertRuleFactory;
use Shared\Alert\Rule\AlertRuleType;
use Shared\Alert\Rule\Collection\AlertRuleTypeCollection;
use Shared\Alert\Rule\SilenceRuleType;
use Shared\Alert\Rule\StateMatchRuleType;
use Shared\Alert\Rule\ThresholdDirection;
use Shared\Alert\Rule\ThresholdRuleType;
use Shared\Alert\Rule\UnavailableRuleType;
use Shared\Alert\Signal\Collection\SignalTypeCollection;
use Shared\Alert\Signal\LightEffectSignalType;
use Shared\Alert\Signal\SignalFactory;
use Shared\Alert\Signal\SignalType;
use Shared\Condition\Collection\ConditionTypeCollection;
use Shared\Condition\CompareConditionType;
use Shared\Condition\ConditionFactory;
use Shared\Condition\ConditionType;
use Shared\Condition\Operator;
use Shared\Notification\Action\ActionRouter;
use Shared\Notification\Condition\ImportanceConditionType;
use Shared\Notification\NotificationDispatcher;
use Shared\Notification\Notifier;
use Shared\Notification\Payload\DestinationPayloadMapper;
use Shared\Notification\Payload\NotificationActionPayloadMapper;
use Shared\Notification\Payload\NotificationPayloadMapper;
use Shared\Notification\Payload\NotificationPayloadSchema;
use Shared\Notification\Sender\ChimeTtsSender;
use Shared\Notification\Sender\Collection\DestinationSenderCollection;
use Shared\Notification\Sender\DestinationSender;
use Shared\Notification\Sender\NotifyServiceSender;
use Shared\Notification\Sender\PhoneTtsSender;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

// Register your own services here; apps receive them by type in their constructor.
return static function (ContainerConfigurator $container): void {
    $services = $container->services();
    $services->defaults()->autowire();

    $services->instanceof(ConditionType::class)->tag('shared.condition_type');
    $services->instanceof(DestinationSender::class)->tag('shared.notification_sender');
    $services->instanceof(AlertRuleType::class)->tag('shared.alert_rule_type');
    $services->instanceof(MitigationType::class)->tag('shared.alert_mitigation_type');
    $services->instanceof(SignalType::class)->tag('shared.alert_signal_type');

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
    $services->set(PhoneTtsSender::class);
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
    $services->set(DestinationPayloadMapper::class);
    $services->set(NotificationPayloadMapper::class);
    $services->set(NotificationActionPayloadMapper::class);

    $services->set(StateMatchRuleType::class);
    $services->set(UnavailableRuleType::class);
    $services->set(SilenceRuleType::class);
    foreach (ThresholdDirection::cases() as $direction) {
        $services->set("shared.alert_rule_type.{$direction->value}", ThresholdRuleType::class)->arg('$direction', $direction);
    }
    $services->set(AlertRuleTypeCollection::class)
        ->factory([AlertRuleTypeCollection::class, 'keyedByName'])
        ->arg('$types', tagged_iterator('shared.alert_rule_type'));
    $services->set(AlertRuleFactory::class);
    $services->set(LightEffectSignalType::class);
    $services->set(SignalTypeCollection::class)
        ->factory([SignalTypeCollection::class, 'keyedByName'])
        ->arg('$types', tagged_iterator('shared.alert_signal_type'));
    $services->set(SignalFactory::class);
    $services->set(EscalationPolicyMapper::class);

    $services->set(TurnOffMitigationType::class);
    $services->set(MitigationTypeCollection::class)
        ->factory([MitigationTypeCollection::class, 'keyedByName'])
        ->arg('$types', tagged_iterator('shared.alert_mitigation_type'));
    $services->set(MitigationFactory::class);
    $services->set(AlertConfigMapper::class);
    $services->set(FaultLocator::class);
    $services->set(AlertMonitor::class);
};
