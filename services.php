<?php

declare(strict_types=1);

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use Shared\Alert\Mitigation\Collection\MitigationTypeCollection;
use Shared\Alert\Mitigation\MitigationType;
use Shared\Alert\Rule\AlertRuleType;
use Shared\Alert\Rule\Collection\AlertRuleTypeCollection;
use Shared\Alert\Rule\ThresholdRuleType;
use Shared\Alert\Signal\Collection\SignalTypeCollection;
use Shared\Alert\Signal\SignalType;
use Shared\Condition\Collection\ConditionTypeCollection;
use Shared\Condition\CompareConditionType;
use Shared\Condition\ConditionType;
use Shared\Condition\Operator;
use Shared\HouseMode\Trigger\Collection\ModeTriggerTypeCollection;
use Shared\HouseMode\Trigger\ModeTriggerType;
use Shared\HouseMode\Trigger\ThresholdTriggerType;
use Shared\Notification\NotificationDispatcher;
use Shared\Notification\Notifier;
use Shared\Notification\Sender\Collection\DestinationSenderCollection;
use Shared\Notification\Sender\DestinationSender;
use Shared\Threshold\ThresholdDirection;
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
    $services->instanceof(ModeTriggerType::class)->tag('shared.mode_trigger_type');

    $services->load('Shared\\', 'src/')
        ->exclude([
            'src/Condition/CompareConditionType.php',
            'src/Alert/Rule/ThresholdRuleType.php',
            'src/HouseMode/Trigger/ThresholdTriggerType.php',
        ]);

    foreach (Operator::cases() as $operator) {
        $services->set("shared.condition_type.{$operator->value}", CompareConditionType::class)
            ->arg('$operator', $operator);
    }
    $services->set(ConditionTypeCollection::class)
        ->factory([ConditionTypeCollection::class, 'keyedByName'])
        ->arg('$types', tagged_iterator('shared.condition_type'));

    $services->set(DestinationSenderCollection::class)
        ->factory([DestinationSenderCollection::class, 'keyedByDestinationType'])
        ->arg('$senders', tagged_iterator('shared.notification_sender'));
    $services->alias(Notifier::class, NotificationDispatcher::class);
    $services->set(Validator::class)
        ->arg('$loader', null)
        ->arg('$max_errors', 20)
        ->arg('$stop_at_first_error', false);
    $services->set(ErrorFormatter::class);

    foreach (ThresholdDirection::cases() as $direction) {
        $services->set("shared.alert_rule_type.{$direction->value}", ThresholdRuleType::class)
            ->arg('$direction', $direction);
    }
    $services->set(AlertRuleTypeCollection::class)
        ->factory([AlertRuleTypeCollection::class, 'keyedByName'])
        ->arg('$types', tagged_iterator('shared.alert_rule_type'));
    $services->set(SignalTypeCollection::class)
        ->factory([SignalTypeCollection::class, 'keyedByName'])
        ->arg('$types', tagged_iterator('shared.alert_signal_type'));
    $services->set(MitigationTypeCollection::class)
        ->factory([MitigationTypeCollection::class, 'keyedByName'])
        ->arg('$types', tagged_iterator('shared.alert_mitigation_type'));

    foreach (ThresholdDirection::cases() as $direction) {
        $services->set("shared.mode_trigger_type.{$direction->value}", ThresholdTriggerType::class)
            ->arg('$direction', $direction);
    }
    $services->set(ModeTriggerTypeCollection::class)
        ->factory([ModeTriggerTypeCollection::class, 'keyedByName'])
        ->arg('$types', tagged_iterator('shared.mode_trigger_type'));
};
