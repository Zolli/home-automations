<?php

declare(strict_types=1);

namespace Shared\Alert\Rule;

use Stewart\Contracts\Time\Duration;

interface RecheckedAlertRule extends AlertRule
{
    public function getRecheckInterval(): Duration;
}
