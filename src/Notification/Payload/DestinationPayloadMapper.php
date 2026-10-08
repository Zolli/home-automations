<?php

declare(strict_types=1);

namespace Shared\Notification\Payload;

use Shared\Condition\AllOf;
use Shared\Condition\ConditionFactory;
use Shared\Condition\InvalidCondition;
use Shared\Notification\Collection\DestinationCollection;
use Shared\Notification\Destination;
use Shared\Notification\DestinationType;
use Shared\Notification\Exception\InvalidNotificationPayload;

/**
 * @phpstan-type DestinationPayload array{type: string, target: string|list<string>, conditions?: list<array<string, mixed>>, options?: array<string, mixed>}
 */
final readonly class DestinationPayloadMapper
{
    public function __construct(
        private NotificationPayloadSchema $schema,
        private ConditionFactory $conditions,
    ) {}

    /**
     * @param list<string|int> $path
     * @throws InvalidNotificationPayload
     */
    public function mapToDestinations(mixed $destinations, array $path): DestinationCollection
    {
        $this->schema->validateDestinations($destinations, $path);
        /** @var list<DestinationPayload> $destinations */
        $mapped = [];

        foreach ($destinations as $index => $destination) {
            $mapped[] = $this->mapDestination($destination, [...$path, $index]);
        }

        return DestinationCollection::fromDestinations($mapped);
    }

    /**
     * @param DestinationPayload $destination
     * @param list<string|int> $path
     * @throws InvalidNotificationPayload
     */
    private function mapDestination(array $destination, array $path): Destination
    {
        return new Destination(
            $this->mapDestinationType($destination['type'], [...$path, 'type']),
            $destination['target'],
            $this->mapConditions($destination['conditions'] ?? [], [...$path, 'conditions']),
            $destination['options'] ?? [],
        );
    }

    /**
     * @param list<string|int> $path
     * @throws InvalidNotificationPayload
     */
    private function mapDestinationType(string $type, array $path): DestinationType
    {
        $destinationType = DestinationType::tryFrom($type);

        if ($destinationType === null) {
            $knownTypes = array_map(static fn(DestinationType $known) => $known->value, DestinationType::cases());
            $reason = \sprintf('Destination type "%s" is unknown, expected one of: %s.', $type, implode(', ', $knownTypes));

            throw InvalidNotificationPayload::forViolation(PayloadViolation::atPath($path, $reason));
        }

        return $destinationType;
    }

    /**
     * @param list<array<string, mixed>> $conditions
     * @param list<string|int> $path
     * @throws InvalidNotificationPayload
     */
    private function mapConditions(array $conditions, array $path): AllOf
    {
        $mapped = [];

        foreach ($conditions as $index => $condition) {
            try {
                $mapped[] = $this->conditions->mapToCondition($condition);
            } catch (InvalidCondition $e) {
                throw InvalidNotificationPayload::forViolation(PayloadViolation::atPath([...$path, $index], $e->getMessage()));
            }
        }

        return new AllOf(...$mapped);
    }
}
