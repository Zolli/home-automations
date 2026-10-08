<?php

declare(strict_types=1);

namespace Shared\Alert\Config;

use Shared\Alert\Reading\Collection\ReadingSourceCollection;
use Shared\Alert\Reading\ReadingSource;
use Stewart\Contracts\Entity\EntityId;
use Stewart\Contracts\Time\Duration;

final readonly class TypedOptions
{
    private const string HOLD_KEY = 'forSeconds';

    private const string HYSTERESIS_KEY = 'hysteresis';

    private const string ENTITY_KEY = 'entity';

    private const string ENTITIES_KEY = 'entities';

    private const string TOPIC_KEY = 'topic';

    private const string TOPICS_KEY = 'topics';

    /** @param array<array-key, mixed> $fields */
    private function __construct(
        public string $kind,
        public string $type,
        private array $fields,
    ) {}

    /**
     * @param array<array-key, mixed> $fields
     * @throws InvalidAlertConfig
     */
    public static function fromArray(string $kind, array $fields): self
    {
        $type = $fields['type'] ?? null;

        if (!\is_string($type) || $type === '') {
            throw InvalidAlertConfig::forMissingType($kind);
        }

        return new self($kind, $type, $fields);
    }

    /** @throws InvalidAlertConfig */
    public function requireEntityId(string $key = self::ENTITY_KEY): EntityId
    {
        return $this->mapToEntityId($this->fields[$key] ?? null, $key, 'an entity id');
    }

    /** @throws InvalidAlertConfig */
    public function requireSources(): ReadingSourceCollection
    {
        $sources = [];

        if ($this->hasOption(self::ENTITY_KEY)) {
            $sources[] = ReadingSource::forEntity($this->requireEntityId());
        }

        foreach ($this->findList(self::ENTITIES_KEY) as $entityId) {
            $sources[] = ReadingSource::forEntity($this->mapToEntityId($entityId, self::ENTITIES_KEY, 'a list of entity ids'));
        }

        if ($this->hasOption(self::TOPIC_KEY)) {
            $sources[] = $this->mapToTopicSource($this->fields[self::TOPIC_KEY], self::TOPIC_KEY);
        }

        foreach ($this->findList(self::TOPICS_KEY) as $topic) {
            $sources[] = $this->mapToTopicSource($topic, self::TOPICS_KEY);
        }

        if ($sources === []) {
            throw InvalidAlertConfig::forMissingSource($this->kind, $this->type, self::ENTITY_KEY, self::ENTITIES_KEY, self::TOPIC_KEY, self::TOPICS_KEY);
        }

        return ReadingSourceCollection::fromSources($sources);
    }

    /** @throws InvalidAlertConfig */
    public function requireTopicSources(): ReadingSourceCollection
    {
        $topics = $this->findList(self::TOPICS_KEY);

        return $topics === []
            ? throw InvalidAlertConfig::forMissingSource($this->kind, $this->type, self::TOPICS_KEY)
            : ReadingSourceCollection::fromSources(array_map(fn(mixed $topic): ReadingSource => $this->mapToTopicSource($topic, self::TOPICS_KEY), $topics));
    }

    /** @throws InvalidAlertConfig */
    public function requireString(string $key): string
    {
        $value = $this->fields[$key] ?? null;

        return \is_string($value) && $value !== '' ? $value : throw $this->createInvalidOption($key, 'a non-empty string');
    }

    /** @throws InvalidAlertConfig */
    public function findString(string $key): ?string
    {
        return $this->hasOption($key) ? $this->requireString($key) : null;
    }

    /** @throws InvalidAlertConfig */
    public function requireNumber(string $key): float
    {
        $value = $this->fields[$key] ?? null;

        return \is_int($value) || \is_float($value) ? (float) $value : throw $this->createInvalidOption($key, 'a number');
    }

    /** @throws InvalidAlertConfig */
    public function findNumber(string $key, float $default): float
    {
        return $this->hasOption($key) ? $this->requireNumber($key) : $default;
    }

    /** @throws InvalidAlertConfig */
    public function findBool(string $key, bool $default): bool
    {
        $value = $this->fields[$key] ?? $default;

        return \is_bool($value) ? $value : throw $this->createInvalidOption($key, 'a boolean');
    }

    /** @throws InvalidAlertConfig */
    public function findHoldDuration(): Duration
    {
        return Duration::seconds($this->findNonNegativeNumber(self::HOLD_KEY));
    }

    /** @throws InvalidAlertConfig */
    public function findHysteresis(): float
    {
        return $this->findNonNegativeNumber(self::HYSTERESIS_KEY);
    }

    /** @throws InvalidAlertConfig */
    private function findNonNegativeNumber(string $key): float
    {
        $value = $this->findNumber($key, 0.0);

        return $value >= 0.0 ? $value : throw $this->createInvalidOption($key, 'a non-negative number');
    }

    private function hasOption(string $key): bool
    {
        return \array_key_exists($key, $this->fields);
    }

    /**
     * @return list<mixed>
     * @throws InvalidAlertConfig
     */
    private function findList(string $key): array
    {
        $values = $this->fields[$key] ?? [];

        return \is_array($values) && array_is_list($values) ? $values : throw $this->createInvalidOption($key, 'a list');
    }

    /** @throws InvalidAlertConfig */
    private function mapToEntityId(mixed $value, string $key, string $expectation): EntityId
    {
        return (\is_string($value) ? EntityId::tryFromString($value) : null) ?? throw $this->createInvalidOption($key, $expectation);
    }

    /** @throws InvalidAlertConfig */
    private function mapToTopicSource(mixed $topic, string $key): ReadingSource
    {
        return \is_string($topic) && trim($topic) !== '' && strpbrk($topic, '+#') === false
            ? ReadingSource::forTopic($topic)
            : throw $this->createInvalidOption($key, 'MQTT topics without wildcards');
    }

    private function createInvalidOption(string $key, string $expectation): InvalidAlertConfig
    {
        return InvalidAlertConfig::forInvalidOption($this->kind, $this->type, $key, $expectation);
    }
}
