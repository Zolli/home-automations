<?php

declare(strict_types=1);

namespace Shared\Alert\Config;

use Shared\Alert\AlertDefinition;
use Shared\Alert\Collection\AlertDefinitionCollection;
use Shared\Alert\Escalation\Collection\EscalationPolicyCollection;
use Shared\Alert\Escalation\EscalationPolicyMapper;
use Shared\Alert\Mitigation\MitigationFactory;
use Shared\Alert\Rule\AlertRuleFactory;
use Shared\Template\TextTemplate;

final readonly class AlertConfigMapper
{
    private const string DEFAULT_MESSAGE = '{reason}';

    public function __construct(
        private EscalationPolicyMapper $policies,
        private AlertRuleFactory $rules,
        private MitigationFactory $mitigations,
    ) {}

    /**
     * @param array<array-key, mixed> $policies
     * @param array<array-key, mixed> $alerts
     * @throws InvalidAlertConfig
     */
    public function mapToDefinitions(array $policies, array $alerts): AlertDefinitionCollection
    {
        $mappedPolicies = $this->policies->mapToPolicies($policies);
        $definitions = [];

        foreach ($alerts as $alert) {
            $definitions[] = $this->mapDefinition(\is_array($alert) ? $alert : [], $mappedPolicies);
        }

        return AlertDefinitionCollection::keyedByAlertId($definitions);
    }

    /**
     * @param array<array-key, mixed> $alert
     * @throws InvalidAlertConfig
     */
    private function mapDefinition(array $alert, EscalationPolicyCollection $policies): AlertDefinition
    {
        $id = $alert['id'] ?? null;

        if (!\is_string($id) || $id === '') {
            throw InvalidAlertConfig::forMissingAlertId();
        }

        $title = $this->mapTemplate($id, 'title', $alert['title'] ?? $id);
        $message = $this->mapTemplate($id, 'message', $alert['message'] ?? self::DEFAULT_MESSAGE);

        $policyName = $alert['policy'] ?? null;
        $policy = \is_string($policyName) ? $policies->find($policyName) : null;

        if ($policy === null) {
            throw InvalidAlertConfig::forInvalidAlert($id, 'policy must name a defined escalation policy.');
        }

        try {
            return new AlertDefinition(
                $id,
                $title,
                $message,
                $this->rules->mapToRule(\is_array($alert['rule'] ?? null) ? $alert['rule'] : []),
                $policy,
                $this->mitigations->mapToMitigations(\is_array($alert['mitigations'] ?? null) ? $alert['mitigations'] : []),
            );
        } catch (InvalidAlertConfig $e) {
            throw InvalidAlertConfig::forInvalidAlert($id, $e->getMessage(), $e);
        }
    }

    /** @throws InvalidAlertConfig */
    private function mapTemplate(string $id, string $key, mixed $text): TextTemplate
    {
        return \is_string($text) && trim($text) !== ''
            ? new TextTemplate($text)
            : throw InvalidAlertConfig::forInvalidAlert($id, "{$key} must be a non-empty string.");
    }
}
