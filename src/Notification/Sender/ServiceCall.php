<?php

declare(strict_types=1);

namespace Shared\Notification\Sender;

use Stewart\Contracts\Service\ServiceTarget;

final readonly class ServiceCall
{
    /** @param array<string, mixed> $data */
    public function __construct(
        public string $domain,
        public string $service,
        public array $data = [],
        public ?ServiceTarget $target = null,
    ) {}

    /** @return array<string, mixed> */
    public function toLogContext(): array
    {
        return array_filter([
            'service' => "{$this->domain}.{$this->service}",
            'data' => $this->data,
            'target' => $this->target?->toArray(),
        ], static fn(mixed $value) => $value !== null);
    }
}
