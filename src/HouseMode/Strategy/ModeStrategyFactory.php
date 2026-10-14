<?php

declare(strict_types=1);

namespace Shared\HouseMode\Strategy;

use Shared\Config\InvalidTypedOptions;
use Shared\Config\TypedOptions;
use Shared\HouseMode\Strategy\Collection\InvalidModeStrategyCollection;
use Shared\HouseMode\Strategy\Collection\ModeStrategyCollection;
use Shared\HouseMode\Strategy\Collection\ModeStrategyTypeCollection;

final readonly class ModeStrategyFactory
{
    private const string ENTITY_KEY_SAFE_NAME = '/\A[a-z][a-z0-9_]{0,39}\z/';

    public function __construct(
        private ModeStrategyTypeCollection $types,
    ) {}

    /**
     * @param array<array-key, mixed> $list
     * @throws InvalidTypedOptions|InvalidModeStrategyCollection
     */
    public function mapToStrategies(array $list): ModeStrategyCollection
    {
        return ModeStrategyCollection::keyedByStrategyName(array_map(
            fn(mixed $fields): ModeStrategy => $this->mapToStrategy(\is_array($fields) ? $fields : []),
            array_values($list),
        ));
    }

    /**
     * @param array<array-key, mixed> $fields
     * @throws InvalidTypedOptions
     */
    private function mapToStrategy(array $fields): ModeStrategy
    {
        $options = TypedOptions::fromArray(ModeStrategyType::KIND, $fields);
        $name    = $options->requireString('name');

        if (preg_match(self::ENTITY_KEY_SAFE_NAME, $name) !== 1) {
            throw InvalidTypedOptions::forInvalidOption(
                $options->kind,
                $options->type,
                'name',
                'lowercase letters, digits and "_", starting with a letter, at most 40 characters',
            );
        }

        return $this->types->requireType($options)->mapToStrategy($name, $options);
    }
}
