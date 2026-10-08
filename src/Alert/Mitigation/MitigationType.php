<?php

declare(strict_types=1);

namespace Shared\Alert\Mitigation;

use Shared\Alert\Config\InvalidAlertConfig;
use Shared\Alert\Config\TypedOptions;

interface MitigationType
{
    public const string KIND = 'Mitigation';

    public function getName(): string;

    /** @throws InvalidAlertConfig */
    public function mapToMitigation(TypedOptions $options): Mitigation;
}
