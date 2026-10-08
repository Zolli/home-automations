<?php

declare(strict_types=1);

namespace Shared\Alert\Signal;

use Shared\Alert\Rule\Fault;
use Stewart\Contracts\Exception\ServiceCallException;
use Stewart\Contracts\HaContext;

interface Signal
{
    /** @throws ServiceCallException */
    public function emit(Fault $fault, HaContext $ha): void;

    public function describe(): string;
}
