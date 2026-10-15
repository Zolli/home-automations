<?php

declare(strict_types=1);

namespace Shared\HouseMode;

use Shared\Config\InvalidTypedOptions;
use Shared\HouseMode\Collection\ModeTriggerCollection;
use Shared\HouseMode\Trigger\ModeTrigger;
use Shared\HouseMode\Trigger\ModeTriggerFactory;
use Stewart\Contracts\Exception\ScheduleException;
use Stewart\Contracts\Schedule\TimeOfDay;

final readonly class ModeTransitionMapper
{
    public function __construct(
        private ModeTriggerFactory $triggers,
    ) {}

    /**
     * @param array<array-key, mixed> $fields
     * @throws InvalidModeConfig
     */
    public function mapToTransition(string $name, array $fields): ModeTransition
    {
        try {
            $strategies   = $this->mapTriggers($fields['strategies'] ?? []);
            $alwaysActive = $this->mapTriggers($fields['alwaysActive'] ?? []);
            // Names key the exposed entities, so they must be unique across both lists.
            ModeTriggerCollection::keyedByStrategyName([...$strategies->listValues(), ...$alwaysActive->listValues()]);
        } catch (InvalidTypedOptions|InvalidModeConfig $e) {
            throw InvalidModeConfig::forInvalidTransition($name, $e->getMessage(), $e);
        }

        $firstStrategy = $strategies->getFirst() ?? throw InvalidModeConfig::forMissingStrategies($name);
        $defaultName   = $fields['defaultStrategy'] ?? $firstStrategy->getName();

        if (!\is_string($defaultName) || $strategies->find($defaultName) === null) {
            throw InvalidModeConfig::forUnknownDefaultStrategy(
                $name,
                \is_string($defaultName) ? $defaultName : get_debug_type($defaultName),
            );
        }

        return new ModeTransition(
            $name,
            $strategies,
            $defaultName,
            $alwaysActive,
            $this->mapWindow($name, $fields['window'] ?? null),
        );
    }

    /** @throws InvalidTypedOptions|InvalidModeConfig */
    private function mapTriggers(mixed $list): ModeTriggerCollection
    {
        return ModeTriggerCollection::keyedByStrategyName(array_map(
            fn(mixed $fields): ModeTrigger => $this->triggers->mapToTrigger(\is_array($fields) ? $fields : []),
            \is_array($list) ? array_values($list) : [],
        ));
    }

    /** @throws InvalidModeConfig */
    private function mapWindow(string $name, mixed $window): ?TransitionWindow
    {
        if ($window === null) {
            return null;
        }

        $from  = \is_array($window) ? $window['from'] ?? null : null;
        $until = \is_array($window) ? $window['until'] ?? null : null;

        if (!\is_string($from) || !\is_string($until)) {
            throw InvalidModeConfig::forInvalidWindow($name);
        }

        try {
            $transitionWindow = new TransitionWindow(TimeOfDay::parse($from), TimeOfDay::parse($until));
        } catch (ScheduleException $e) {
            throw InvalidModeConfig::forInvalidWindow($name, $e);
        }

        return $transitionWindow->from->equals($transitionWindow->until)
            ? throw InvalidModeConfig::forInvalidWindow($name)
            : $transitionWindow;
    }
}
