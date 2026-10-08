<?php

declare(strict_types=1);

namespace Shared\Alert\Reading;

use Closure;
use Shared\Alert\MonitoringContext;
use Shared\Alert\Reading\Collection\ReadingCollection;
use Shared\Alert\Reading\Collection\ReadingSourceCollection;
use Stewart\Contracts\Mqtt\MqttMessage;
use Stewart\Contracts\Selector\Selector;
use Stewart\Contracts\Subscription;
use Stewart\Contracts\Time\Instant;

final class ReadingFeed
{
    private readonly ?Selector $entitySelector;

    /** @var list<string> */
    private readonly array $topics;

    /** @var array<string, Reading> */
    private array $latestMqttReadings = [];

    /** @var list<Subscription> */
    private array $subscriptions = [];

    private ?Instant $watchingSince = null;

    public function __construct(
        ReadingSourceCollection $sources,
        private readonly MonitoringContext $context,
    ) {
        $entityIds = $sources->listKeysOfType(ReadingSourceType::Entity);
        $this->entitySelector = $entityIds === [] ? null : Selector::anyOf(...$entityIds);
        $this->topics = $sources->listKeysOfType(ReadingSourceType::MqttTopic);
    }

    /** @param Closure(): void $whenChanged */
    public function startWatching(Closure $whenChanged): void
    {
        if ($this->watchingSince !== null) {
            return;
        }

        $this->watchingSince = $this->context->clock->getNow();

        if ($this->entitySelector !== null) {
            $this->subscriptions[] = $this->context->ha->watchStateChanges($this->entitySelector)->subscribe(static fn() => $whenChanged());
        }

        foreach ($this->topics as $topic) {
            $this->subscriptions[] = $this->context->mqtt->watchMessages($topic)->subscribe(
                function (MqttMessage $message) use ($whenChanged): void {
                    $this->latestMqttReadings[$message->topic] = Reading::fromMqttPayload($message->topic, $message->payload, $this->context->clock->getNow());
                    $whenChanged();
                },
            );
        }
    }

    public function stopWatching(): void
    {
        foreach ($this->subscriptions as $subscription) {
            $subscription->unsubscribe();
        }

        $this->subscriptions = [];
        $this->latestMqttReadings = [];
        $this->watchingSince = null;
    }

    public function readSnapshot(): ReadingSnapshot
    {
        $now = $this->context->clock->getNow();
        $entityReadings = $this->entitySelector === null
            ? []
            : $this->context->ha->listStates($this->entitySelector)->mapToList(Reading::fromEntityState(...));

        return new ReadingSnapshot(
            ReadingCollection::keyedBySource([...$entityReadings, ...array_values($this->latestMqttReadings)]),
            $now,
            $this->watchingSince ?? $now,
        );
    }
}
