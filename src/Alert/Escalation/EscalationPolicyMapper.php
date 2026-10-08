<?php

declare(strict_types=1);

namespace Shared\Alert\Escalation;

use Shared\Alert\Config\InvalidAlertConfig;
use Shared\Alert\Escalation\Collection\EscalationPolicyCollection;
use Shared\Alert\Escalation\Collection\EscalationStepCollection;
use Shared\Alert\Signal\Signal;
use Shared\Alert\Signal\SignalFactory;
use Shared\Condition\AllOf;
use Shared\Condition\ConditionFactory;
use Shared\Condition\InvalidCondition;
use Shared\Notification\Collection\DestinationCollection;
use Shared\Notification\Exception\InvalidNotificationPayload;
use Shared\Notification\Importance;
use Shared\Notification\Payload\DestinationPayloadMapper;
use Stewart\Contracts\Time\Duration;

final readonly class EscalationPolicyMapper
{
    public function __construct(
        private DestinationPayloadMapper $destinations,
        private ConditionFactory $conditions,
        private SignalFactory $signals,
    ) {}

    /**
     * @param array<array-key, mixed> $policies
     * @throws InvalidAlertConfig
     */
    public function mapToPolicies(array $policies): EscalationPolicyCollection
    {
        $mapped = [];

        foreach ($policies as $name => $policy) {
            $mapped[] = $this->mapPolicy((string) $name, \is_array($policy) ? $policy : []);
        }

        return EscalationPolicyCollection::keyedByName($mapped);
    }

    /**
     * @param array<array-key, mixed> $policy
     * @throws InvalidAlertConfig
     */
    private function mapPolicy(string $name, array $policy): EscalationPolicy
    {
        $steps = $policy['steps'] ?? null;
        $acknowledgeOnDismiss = $policy['acknowledgeOnDismiss'] ?? false;

        if (!\is_array($steps)) {
            throw InvalidAlertConfig::forInvalidPolicy($name, 'steps must be a list.');
        }

        if (!\is_bool($acknowledgeOnDismiss)) {
            throw InvalidAlertConfig::forInvalidPolicy($name, 'acknowledgeOnDismiss must be a boolean.');
        }

        $mappedSteps = [];

        foreach (array_values($steps) as $index => $step) {
            $mappedSteps[] = $this->mapStep($name, $index, \is_array($step) ? $step : []);
        }

        try {
            return new EscalationPolicy($name, EscalationStepCollection::fromSteps($mappedSteps), $acknowledgeOnDismiss);
        } catch (InvalidEscalationPolicy $e) {
            throw InvalidAlertConfig::forInvalidPolicy($name, $e->getMessage(), $e);
        }
    }

    /**
     * @param array<array-key, mixed> $step
     * @throws InvalidAlertConfig
     */
    private function mapStep(string $policyName, int $index, array $step): EscalationStep
    {
        $path = "steps[{$index}]";
        $importance = $step['importance'] ?? Importance::Critical->value;
        $signal = \array_key_exists('signal', $step) ? $this->mapSignal($policyName, "{$path}.signal", $step['signal']) : null;
        $destinations = $signal !== null && !\array_key_exists('destinations', $step)
            ? DestinationCollection::empty()
            : $this->mapDestinations($policyName, $index, $step['destinations'] ?? null);

        try {
            return new EscalationStep(
                Duration::seconds($this->requireSeconds($policyName, "{$path}.afterSeconds", $step['afterSeconds'] ?? 0)),
                $destinations,
                (\is_string($importance) ? Importance::tryFrom($importance) : null)
                    ?? throw InvalidAlertConfig::forInvalidPolicy($policyName, "{$path}.importance is unknown."),
                \array_key_exists('repeat', $step) ? $this->mapRepetition($policyName, "{$path}.repeat", $step['repeat']) : null,
                $signal,
            );
        } catch (InvalidEscalationPolicy $e) {
            throw InvalidAlertConfig::forInvalidPolicy($policyName, "{$path}: {$e->getMessage()}", $e);
        }
    }

    /** @throws InvalidAlertConfig */
    private function mapDestinations(string $policyName, int $index, mixed $destinations): DestinationCollection
    {
        try {
            return $this->destinations->mapToDestinations($destinations, ['steps', $index, 'destinations']);
        } catch (InvalidNotificationPayload $e) {
            throw InvalidAlertConfig::forInvalidPolicy($policyName, $e->getMessage(), $e);
        }
    }

    /** @throws InvalidAlertConfig */
    private function mapSignal(string $policyName, string $path, mixed $signal): Signal
    {
        try {
            return $this->signals->mapToSignal(\is_array($signal) ? $signal : []);
        } catch (InvalidAlertConfig $e) {
            throw InvalidAlertConfig::forInvalidPolicy($policyName, "{$path}: {$e->getMessage()}", $e);
        }
    }

    /**
     * @throws InvalidAlertConfig
     * @throws InvalidEscalationPolicy
     */
    private function mapRepetition(string $policyName, string $path, mixed $repeat): StepRepetition
    {
        if (!\is_array($repeat)) {
            throw InvalidAlertConfig::forInvalidPolicy($policyName, "{$path} must be a map.");
        }

        $times = $repeat['times'] ?? null;

        return new StepRepetition(
            Duration::seconds($this->requireSeconds($policyName, "{$path}.everySeconds", $repeat['everySeconds'] ?? null)),
            $times === null || \is_int($times) ? $times : throw InvalidAlertConfig::forInvalidPolicy($policyName, "{$path}.times must be an integer."),
            $this->mapConditions($policyName, "{$path}.while", $repeat['while'] ?? []),
        );
    }

    /** @throws InvalidAlertConfig */
    private function mapConditions(string $policyName, string $path, mixed $conditions): AllOf
    {
        if (!\is_array($conditions)) {
            throw InvalidAlertConfig::forInvalidPolicy($policyName, "{$path} must be a list.");
        }

        $mapped = [];

        foreach ($conditions as $condition) {
            try {
                $mapped[] = $this->conditions->mapToCondition(\is_array($condition) ? $condition : []);
            } catch (InvalidCondition $e) {
                throw InvalidAlertConfig::forInvalidPolicy($policyName, "{$path}: {$e->getMessage()}", $e);
            }
        }

        return new AllOf(...$mapped);
    }

    /** @throws InvalidAlertConfig */
    private function requireSeconds(string $policyName, string $path, mixed $seconds): float
    {
        return (\is_int($seconds) || \is_float($seconds)) && $seconds >= 0
            ? (float) $seconds
            : throw InvalidAlertConfig::forInvalidPolicy($policyName, "{$path} must be a non-negative number.");
    }
}
