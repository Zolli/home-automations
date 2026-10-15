<?php

declare(strict_types=1);

namespace Shared\HouseMode\Trigger;

use Shared\Config\InvalidTypedOptions;
use Shared\Config\TypedOptions;
use Shared\HouseMode\Trigger\Collection\ModeTriggerTypeCollection;

final readonly class ModeTriggerFactory
{
    private const string NAME_KEY             = 'name';
    private const string ENTITY_KEY_SAFE_NAME = '/\A[a-z][a-z0-9_]{0,39}\z/';

    public function __construct(
        private ModeTriggerTypeCollection $types,
    ) {}

    /**
     * @param array<array-key, mixed> $fields
     * @throws InvalidTypedOptions
     */
    public function mapToTrigger(array $fields): ModeTrigger
    {
        $options = TypedOptions::fromArray(ModeTriggerType::KIND, $fields);
        $name    = $this->requireName($options);

        return $this->types->requireType($options)->mapToTrigger($name, $options);
    }

    /** @throws InvalidTypedOptions */
    private function requireName(TypedOptions $options): string
    {
        $name = $options->requireString(self::NAME_KEY);

        return preg_match(self::ENTITY_KEY_SAFE_NAME, $name) === 1
            ? $name
            : throw InvalidTypedOptions::forInvalidOption(
                $options->kind,
                $options->type,
                self::NAME_KEY,
                'lowercase letters, digits and "_", starting with a letter, at most 40 characters',
            );
    }
}
