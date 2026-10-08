<?php

declare(strict_types=1);

namespace App\Tests\Shared\Alert\Escalation;

use RuntimeException;
use Shared\Alert\Rule\Fault;
use Shared\Alert\Signal\Signal;
use Stewart\Contracts\HaContext;

final class SignalRecorder implements Signal
{
    /** @var list<string> */
    public array $emittedFor = [];

    public function __construct(private readonly bool $failing = false) {}

    public function emit(Fault $fault, HaContext $ha): void
    {
        if ($this->failing) {
            throw new RuntimeException('Light offline');
        }

        $this->emittedFor[] = $fault->source->key;
    }

    public function describe(): string
    {
        return 'recorded signal';
    }
}
