<?php

declare(strict_types=1);

namespace Shared\Config;

use Stewart\Contracts\Entity\EntityId;
use Stewart\Contracts\Exception\ScheduleException;
use Stewart\Contracts\Schedule\TimeOfDay;
use Stewart\Contracts\Time\Duration;

final readonly class TypedOptions
{
    /** @param array<array-key, mixed> $fields */
    private function __construct(
        public string $kind,
        public string $type,
        private array $fields,
    ) {}

    /**
     * @param array<array-key, mixed> $fields
     * @throws InvalidTypedOptions
     */
    public static function fromArray(string $kind, array $fields): self
    {
        $type = $fields['type'] ?? null;

        if (!\is_string($type) || $type === '') {
            throw InvalidTypedOptions::forMissingType($kind);
        }

        return new self($kind, $type, $fields);
    }

    public function hasOption(string $key): bool
    {
        return \array_key_exists($key, $this->fields);
    }

    /** @throws InvalidTypedOptions */
    public function requireValue(string $key): mixed
    {
        return $this->hasOption($key)
            ? $this->fields[$key]
            : throw InvalidTypedOptions::forMissingOption($this->kind, $this->type, $key);
    }

    /** @throws InvalidTypedOptions */
    public function requireEntityId(string $key): EntityId
    {
        return $this->mapToEntityId($this->fields[$key] ?? null, $key, 'an entity id');
    }

    /**
     * @return list<EntityId>
     * @throws InvalidTypedOptions
     */
    public function findEntityIds(string $key): array
    {
        return array_map(
            fn(mixed $value): EntityId => $this->mapToEntityId($value, $key, 'a list of entity ids'),
            $this->findList($key),
        );
    }

    /** @throws InvalidTypedOptions */
    public function requireString(string $key): string
    {
        $value = $this->fields[$key] ?? null;

        return \is_string($value) && $value !== ''
            ? $value
            : throw $this->createInvalidOption($key, 'a non-empty string');
    }

    /** @throws InvalidTypedOptions */
    public function findString(string $key): ?string
    {
        return $this->hasOption($key) ? $this->requireString($key) : null;
    }

    /** @throws InvalidTypedOptions */
    public function requireNumber(string $key): float
    {
        $value = $this->fields[$key] ?? null;

        return \is_int($value) || \is_float($value)
            ? (float) $value
            : throw $this->createInvalidOption($key, 'a number');
    }

    /** @throws InvalidTypedOptions */
    public function findNonNegativeNumber(string $key): float
    {
        $value = $this->hasOption($key) ? $this->requireNumber($key) : 0.0;

        return $value >= 0.0 ? $value : throw $this->createInvalidOption($key, 'a non-negative number');
    }

    /** @throws InvalidTypedOptions */
    public function findDuration(string $secondsKey): Duration
    {
        return Duration::seconds($this->findNonNegativeNumber($secondsKey));
    }

    /** @throws InvalidTypedOptions */
    public function requireTimeOfDay(string $key): TimeOfDay
    {
        try {
            return TimeOfDay::parse($this->requireString($key));
        } catch (ScheduleException) {
            throw $this->createInvalidOption($key, 'a time of day like 07:00');
        }
    }

    /** @throws InvalidTypedOptions */
    public function findBool(string $key, bool $default): bool
    {
        $value = $this->fields[$key] ?? $default;

        return \is_bool($value) ? $value : throw $this->createInvalidOption($key, 'a boolean');
    }

    /**
     * @return list<mixed>
     * @throws InvalidTypedOptions
     */
    public function findList(string $key): array
    {
        $values = $this->fields[$key] ?? [];

        return \is_array($values) && array_is_list($values)
            ? $values
            : throw $this->createInvalidOption($key, 'a list');
    }

    /** @throws InvalidTypedOptions */
    private function mapToEntityId(mixed $value, string $key, string $expectation): EntityId
    {
        return (\is_string($value) ? EntityId::tryFromString($value) : null)
            ?? throw $this->createInvalidOption($key, $expectation);
    }

    private function createInvalidOption(string $key, string $expectation): InvalidTypedOptions
    {
        return InvalidTypedOptions::forInvalidOption($this->kind, $this->type, $key, $expectation);
    }
}
