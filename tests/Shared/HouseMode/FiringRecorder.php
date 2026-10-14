<?php

declare(strict_types=1);

namespace App\Tests\Shared\HouseMode;

final class FiringRecorder
{
    /** @var list<string> */
    public array $firedStrategies = [];

    public function recordFiring(string $strategyName): void
    {
        $this->firedStrategies[] = $strategyName;
    }
}
