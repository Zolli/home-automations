<?php

declare(strict_types=1);

namespace Shared\Alert;

use Shared\Alert\Escalation\EscalationPolicy;
use Shared\Alert\Mitigation\Collection\MitigationCollection;
use Shared\Alert\Rule\AlertRule;
use Shared\Template\TextTemplate;

final readonly class AlertDefinition
{
    public function __construct(
        public string $id,
        public TextTemplate $title,
        public TextTemplate $message,
        public AlertRule $rule,
        public EscalationPolicy $policy,
        public MitigationCollection $mitigations,
    ) {}
}
