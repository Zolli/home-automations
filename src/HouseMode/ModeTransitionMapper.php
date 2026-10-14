<?php

declare(strict_types=1);

namespace Shared\HouseMode;

use Shared\Config\InvalidTypedOptions;
use Shared\Config\TypedOptions;
use Shared\HouseMode\Collection\ModeTriggerCollection;
use Shared\HouseMode\Trigger\FixedTimeTrigger;
use Shared\HouseMode\Trigger\ModeTrigger;
use Shared\HouseMode\Trigger\StateTrigger;
use Shared\HouseMode\Trigger\SunEventTrigger;
use Shared\HouseMode\Trigger\ThresholdRange;
use Shared\HouseMode\Trigger\ThresholdTrigger;
use Shared\Threshold\ThresholdDirection;
use Stewart\Contracts\Exception\ScheduleException;
use Stewart\Contracts\Schedule\TimeOfDay;
use Stewart\Contracts\Sun\SunEvent;
use Stewart\Contracts\Time\Duration;
use Stewart\Contracts\Time\SunOffset;

final readonly class ModeTransitionMapper
{
    private const string STRATEGY_KIND        = 'Mode strategy';
    private const string HOLD_KEY             = 'forSeconds';
    private const string ENTITY_KEY_SAFE_NAME = '/\A[a-z][a-z0-9_]{0,39}\z/';
    private const array STRATEGY_TYPES        = ['fixed_time', 'state', 'above', 'below', 'sun'];

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
            fn(mixed $fields): ModeTrigger => $this->mapTrigger(\is_array($fields) ? $fields : []),
            \is_array($list) ? array_values($list) : [],
        ));
    }

    /**
     * @param array<array-key, mixed> $fields
     * @throws InvalidTypedOptions
     */
    private function mapTrigger(array $fields): ModeTrigger
    {
        $options = TypedOptions::fromArray(self::STRATEGY_KIND, $fields);
        $name    = $options->requireString('name');

        if (preg_match(self::ENTITY_KEY_SAFE_NAME, $name) !== 1) {
            throw InvalidTypedOptions::forInvalidOption(
                $options->kind,
                $options->type,
                'name',
                'lowercase letters, digits and "_", starting with a letter, at most 40 characters',
            );
        }

        return match ($options->type) {
            'fixed_time'     => new FixedTimeTrigger($name, $options->requireTimeOfDay('time')),
            'state'          => new StateTrigger(
                $name,
                $options->requireEntityId('entity'),
                $options->findString('state') ?? 'on',
                $options->findPositiveDuration(self::HOLD_KEY),
            ),
            'above', 'below' => $this->mapThresholdTrigger($name, ThresholdDirection::from($options->type), $options),
            'sun'            => new SunEventTrigger($name, $this->mapSunEvent($options), $this->mapSunOffset($options)),
            default          => throw InvalidTypedOptions::forUnknownType(
                $options->kind,
                $options->type,
                self::STRATEGY_TYPES,
            ),
        };
    }

    /** @throws InvalidTypedOptions */
    private function mapThresholdTrigger(
        string $name,
        ThresholdDirection $direction,
        TypedOptions $options,
    ): ThresholdTrigger {
        $min       = $options->hasOption('min') ? $options->requireNumber('min') : 0.0;
        $max       = $options->requireNumber('max');
        $step      = $options->hasOption('step') ? $options->requireNumber('step') : 1.0;
        $threshold = $options->requireNumber('threshold');
        $range     = new ThresholdRange($min, $max, $step, $options->findString('unit'));

        if ($max <= $min) {
            throw $this->createInvalidOption($options, 'max', 'a number above min');
        }

        if ($step <= 0.0) {
            throw $this->createInvalidOption($options, 'step', 'a positive number');
        }

        if (!$range->includes($threshold)) {
            throw $this->createInvalidOption($options, 'threshold', 'a number within min and max');
        }

        return new ThresholdTrigger(
            $name,
            $options->requireEntityId('entity'),
            $direction,
            $threshold,
            $range,
            $options->findNonNegativeNumber('hysteresis'),
            $options->findPositiveDuration(self::HOLD_KEY),
        );
    }

    /** @throws InvalidTypedOptions */
    private function mapSunEvent(TypedOptions $options): SunEvent
    {
        return SunEvent::tryFrom($options->requireString('event'))
            ?? throw $this->createInvalidOption($options, 'event', 'a sun event like sunrise');
    }

    /** @throws InvalidTypedOptions */
    private function mapSunOffset(TypedOptions $options): ?SunOffset
    {
        $offsetSeconds = $options->hasOption('offsetSeconds') ? $options->requireNumber('offsetSeconds') : 0.0;
        $magnitude     = Duration::seconds(abs($offsetSeconds));

        return match (true) {
            $offsetSeconds < 0.0 => SunOffset::before($magnitude),
            $offsetSeconds > 0.0 => SunOffset::after($magnitude),
            default              => null,
        };
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

    private function createInvalidOption(TypedOptions $options, string $key, string $expectation): InvalidTypedOptions
    {
        return InvalidTypedOptions::forInvalidOption($options->kind, $options->type, $key, $expectation);
    }
}
