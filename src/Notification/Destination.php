<?php

declare(strict_types=1);

namespace Shared\Notification;

use Shared\Condition\AllOf;
use Shared\Condition\Condition;

final readonly class Destination
{
    /** @var non-empty-list<string> */
    public array $targets;

    /**
     * @param string|list<string> $targets
     * @param array<string, mixed> $options
     */
    public function __construct(
        public DestinationType $type,
        string|array $targets,
        public Condition $condition = new AllOf(),
        public array $options = [],
    ) {
        $targets = array_values(array_unique(array_filter(
            array_map(trim(...), (array) $targets),
            static fn(string $target) => $target !== '',
        )));

        if ($targets === []) {
            throw InvalidDestination::forMissingTargets($type);
        }

        $this->targets = $targets;
    }

    public function withAppendedConditions(Condition ...$conditions): self
    {
        return new self($this->type, $this->targets, new AllOf($this->condition, ...$conditions), $this->options);
    }
}
