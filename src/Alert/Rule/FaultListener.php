<?php

declare(strict_types=1);

namespace Shared\Alert\Rule;

interface FaultListener
{
    public function raiseFault(Fault $fault): void;

    public function clearFault(): void;
}
