<?php

declare(strict_types=1);

namespace Shared\Alert\Config;

use Shared\Alert\Reading\Collection\ReadingSourceCollection;
use Shared\Alert\Reading\ReadingSource;
use Shared\Config\InvalidTypedOptions;
use Shared\Config\TypedOptions;

final readonly class ReadingSourceMapper
{
    private const string ENTITY_KEY   = 'entity';
    private const string ENTITIES_KEY = 'entities';
    private const string TOPIC_KEY    = 'topic';
    private const string TOPICS_KEY   = 'topics';

    /** @throws InvalidTypedOptions */
    public function mapToSources(TypedOptions $options): ReadingSourceCollection
    {
        $sources = [];

        if ($options->hasOption(self::ENTITY_KEY)) {
            $sources[] = ReadingSource::forEntity($options->requireEntityId(self::ENTITY_KEY));
        }

        foreach ($options->findEntityIds(self::ENTITIES_KEY) as $entityId) {
            $sources[] = ReadingSource::forEntity($entityId);
        }

        if ($options->hasOption(self::TOPIC_KEY)) {
            $sources[] = $this->mapToTopicSource($options, self::TOPIC_KEY, $options->requireValue(self::TOPIC_KEY));
        }

        foreach ($options->findList(self::TOPICS_KEY) as $topic) {
            $sources[] = $this->mapToTopicSource($options, self::TOPICS_KEY, $topic);
        }

        if ($sources === []) {
            throw InvalidTypedOptions::forMissingOption(
                $options->kind,
                $options->type,
                self::ENTITY_KEY,
                self::ENTITIES_KEY,
                self::TOPIC_KEY,
                self::TOPICS_KEY,
            );
        }

        return ReadingSourceCollection::fromSources($sources);
    }

    /** @throws InvalidTypedOptions */
    public function mapToTopicSources(TypedOptions $options): ReadingSourceCollection
    {
        $topics = $options->findList(self::TOPICS_KEY);

        return $topics === []
            ? throw InvalidTypedOptions::forMissingOption($options->kind, $options->type, self::TOPICS_KEY)
            : ReadingSourceCollection::fromSources(array_map(
                fn(mixed $topic): ReadingSource => $this->mapToTopicSource($options, self::TOPICS_KEY, $topic),
                $topics,
            ));
    }

    /** @throws InvalidTypedOptions */
    private function mapToTopicSource(TypedOptions $options, string $key, mixed $topic): ReadingSource
    {
        return \is_string($topic) && trim($topic) !== '' && strpbrk($topic, '+#') === false
            ? ReadingSource::forTopic($topic)
            : throw InvalidTypedOptions::forInvalidOption(
                $options->kind,
                $options->type,
                $key,
                'MQTT topics without wildcards',
            );
    }
}
