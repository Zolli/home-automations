<?php

declare(strict_types=1);

namespace Shared\HouseMode\Config;

use Shared\Config\InvalidTypedOptions;
use Shared\HouseMode\ModeTransition;
use Shared\HouseMode\Strategy\Collection\InvalidModeStrategyCollection;
use Shared\HouseMode\Strategy\Collection\ModeStrategyCollection;
use Shared\HouseMode\Strategy\ModeStrategyFactory;
use Shared\HouseMode\TransitionWindow;
use Stewart\Contracts\Exception\ScheduleException;
use Stewart\Contracts\Schedule\TimeOfDay;

final readonly class ModeTransitionMapper
{
    public function __construct(
        private ModeStrategyFactory $strategies,
    ) {}

    /**
     * @param array<array-key, mixed> $fields
     * @throws InvalidModeConfig
     */
    public function mapToTransition(string $name, array $fields): ModeTransition
    {
        try {
            $strategies   = $this->strategies->mapToStrategies($this->readList($fields, 'strategies'));
            $alwaysActive = $this->strategies->mapToStrategies($this->readList($fields, 'alwaysActive'));
            // Names key the exposed entities, so they must be unique across both lists.
            ModeStrategyCollection::keyedByStrategyName([...$strategies->listValues(), ...$alwaysActive->listValues()]);
        } catch (InvalidTypedOptions|InvalidModeStrategyCollection $e) {
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

    /**
     * @param array<array-key, mixed> $fields
     * @return array<array-key, mixed>
     */
    private function readList(array $fields, string $key): array
    {
        return \is_array($fields[$key] ?? null) ? $fields[$key] : [];
    }
}
