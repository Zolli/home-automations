<?php

declare(strict_types=1);

namespace Shared\Alert\Rule;

use Shared\Alert\Reading\Collection\ReadingSourceCollection;
use Shared\Alert\Reading\ReadingSnapshot;
use Stewart\Contracts\Time\Duration;

interface AlertRule
{
    public function listSources(): ReadingSourceCollection;

    public function getHoldDuration(): Duration;

    public function findFault(ReadingSnapshot $snapshot): ?Fault;

    public function isRecovered(ReadingSnapshot $snapshot): bool;

    public function describe(): string;
}
