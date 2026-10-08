<?php

declare(strict_types=1);

namespace Shared\Alert\Mitigation;

use Shared\Alert\Config\TypedOptions;

final readonly class TurnOffMitigationType implements MitigationType
{
    public function getName(): string
    {
        return 'turn-off';
    }

    public function mapToMitigation(TypedOptions $options): Mitigation
    {
        return new TurnOffMitigation($options->requireEntityId());
    }
}
