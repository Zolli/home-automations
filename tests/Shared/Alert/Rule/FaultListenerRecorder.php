<?php

declare(strict_types=1);

namespace App\Tests\Shared\Alert\Rule;

use Shared\Alert\Rule\Fault;
use Shared\Alert\Rule\FaultListener;

final class FaultListenerRecorder implements FaultListener
{
    /** @var list<Fault|null> */
    public array $signals = [];

    public function raiseFault(Fault $fault): void
    {
        $this->signals[] = $fault;
    }

    public function clearFault(): void
    {
        $this->signals[] = null;
    }

    /** @return list<string> */
    public function listReasons(): array
    {
        return array_map(static fn(?Fault $fault): string => $fault->reason ?? 'cleared', $this->signals);
    }
}
