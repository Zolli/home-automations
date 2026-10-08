<?php

declare(strict_types=1);

namespace Shared\Notification\Payload;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Helper;
use Opis\JsonSchema\Schema;
use Opis\JsonSchema\Validator;
use Shared\Notification\Exception\InvalidNotificationPayload;
use Shared\Notification\Payload\Collection\PayloadViolationCollection;

final readonly class NotificationPayloadSchema
{
    public const string FILE = __DIR__ . '/notification.schema.json';

    private const string DESTINATIONS_POINTER = '#/$defs/destinations';

    private Schema $schema;

    private string $destinationsSchemaUri;

    public function __construct(
        private Validator $validator,
        private ErrorFormatter $errorFormatter,
    ) {
        $document = json_decode((string) file_get_contents(self::FILE), flags: \JSON_THROW_ON_ERROR);
        $this->schema = $validator->loader()->loadObjectSchema($document);
        $this->destinationsSchemaUri = $document->{'$id'} . self::DESTINATIONS_POINTER;
    }

    /** @throws InvalidNotificationPayload */
    public function validatePayload(mixed $payload): void
    {
        $this->validateAgainst($this->schema, $payload, []);
    }

    /**
     * @param list<string|int> $path
     * @throws InvalidNotificationPayload
     */
    public function validateDestinations(mixed $destinations, array $path): void
    {
        $this->validateAgainst($this->destinationsSchemaUri, $destinations, $path);
    }

    /**
     * @param list<string|int> $path
     * @throws InvalidNotificationPayload
     */
    private function validateAgainst(Schema|string $schema, mixed $data, array $path): void
    {
        $error = $this->validator->validate(Helper::convertAssocArrayToObject($data), $schema)->error();

        if ($error !== null) {
            throw InvalidNotificationPayload::forViolations(PayloadViolationCollection::fromViolations($this->mapToViolations($error, $path)));
        }
    }

    /**
     * @param list<string|int> $prefix
     * @return iterable<PayloadViolation>
     */
    private function mapToViolations(ValidationError $error, array $prefix): iterable
    {
        if ($error->subErrors() !== []) {
            foreach ($error->subErrors() as $subError) {
                yield from $this->mapToViolations($subError, $prefix);
            }

            return;
        }

        $path = [...$prefix, ...array_values($error->data()->fullPath())];
        $args = $error->args();

        yield from match ($error->keyword()) {
            'required' => $this->mapEachProperty($path, $args['missing'], 'is required'),
            'additionalProperties' => $this->mapEachProperty(
                $path,
                array_values(array_diff($args['properties'], $this->listDeclaredProperties($error))),
                'is not allowed',
            ),
            'properties' => [PayloadViolation::atPath([...$path, $args['property']], 'is not allowed')],
            default => [PayloadViolation::atPath($path, $this->describeError($error))],
        };
    }

    /**
     * @param list<string|int> $path
     * @param list<string> $properties
     * @return iterable<PayloadViolation>
     */
    private function mapEachProperty(array $path, array $properties, string $reason): iterable
    {
        foreach ($properties as $property) {
            yield PayloadViolation::atPath([...$path, $property], $reason);
        }
    }

    /** @return list<string> */
    private function listDeclaredProperties(ValidationError $error): array
    {
        $properties = $error->schema()->info()->data()->properties ?? null;

        return \is_object($properties) ? array_keys(get_object_vars($properties)) : [];
    }

    private function describeError(ValidationError $error): string
    {
        $args = $error->args();

        return match ($error->keyword()) {
            'type' => 'must be of type ' . implode(' or ', (array) $args['expected']),
            'enum' => 'must be one of: ' . implode(', ', array_map(strval(...), (array) ($error->schema()->info()->data()->enum ?? []))),
            'minItems' => "must have at least {$args['min']} items",
            'maxItems' => "must have at most {$args['max']} items",
            'minLength' => $args['min'] === 1 ? 'must not be empty' : "must have at least {$args['min']} characters",
            'pattern' => 'has an invalid format',
            default => lcfirst($this->errorFormatter->formatErrorMessage($error)),
        };
    }
}
