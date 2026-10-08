<?php

declare(strict_types=1);

namespace Shared\Alert\Mitigation;

use Stewart\Contracts\Exception\ServiceCallException;
use Stewart\Contracts\HaContext;

interface Mitigation
{
    /** @throws ServiceCallException */
    public function mitigate(HaContext $ha): void;

    public function describe(): string;
}
