<?php

declare(strict_types=1);

namespace Shared\HouseMode;

use Shared\HouseMode\Trigger\ThresholdRange;
use Stewart\Contracts\Exception\ExposureException;
use Stewart\Contracts\Exposure\EntityCategory;
use Stewart\Contracts\Exposure\ExposedEntity;
use Stewart\Contracts\Exposure\ExposedNumber;
use Stewart\Contracts\Exposure\ExposedSelect;
use Stewart\Contracts\Exposure\ExposedTime;
use Stewart\Contracts\Exposure\SelectConfig;
use Stewart\Contracts\Exposure\TimeConfig;
use Stewart\Contracts\Schedule\TimeOfDay;

final readonly class TransitionContext
{
    private const string STRATEGY_SELECT_KEY = 'strategy';
    private const string TIME_KEY            = 'time';
    private const string THRESHOLD_KEY       = 'threshold';

    public function __construct(
        public ModeContext $mode,
        public string $transitionName,
    ) {}

    /**
     * @param list<string> $strategyNames
     * @throws ExposureException
     */
    public function exposeStrategySelect(array $strategyNames, string $defaultStrategyName): ExposedSelect
    {
        $select = $this->mode->exposure->exposeSelect(
            $this->composeKey(self::STRATEGY_SELECT_KEY),
            new SelectConfig(
                $strategyNames,
                $this->composeName(self::STRATEGY_SELECT_KEY),
                entityCategory: EntityCategory::Config,
            ),
            $this->mode->device,
        );

        if ($this->isKnownToHomeAssistant($select) && !\in_array($select->getOption(), $strategyNames, true)) {
            $select->setOption($defaultStrategyName);
        }

        return $select;
    }

    /** @throws ExposureException */
    public function exposeTime(string $strategyName, TimeOfDay $initialTime): ExposedTime
    {
        $exposedTime = $this->mode->exposure->exposeTime(
            $this->composeKey($strategyName, self::TIME_KEY),
            new TimeConfig($this->composeName($strategyName), entityCategory: EntityCategory::Config),
            $this->mode->device,
        );

        if ($this->isKnownToHomeAssistant($exposedTime) && $exposedTime->getValue() === null) {
            $exposedTime->setValue($initialTime);
        }

        return $exposedTime;
    }

    /** @throws ExposureException */
    public function exposeThreshold(string $strategyName, ThresholdRange $range, float $initialThreshold): ExposedNumber
    {
        $exposedThreshold = $this->mode->exposure->exposeNumber(
            $this->composeKey($strategyName, self::THRESHOLD_KEY),
            $range->mapToNumberConfig($this->composeName($strategyName)),
            $this->mode->device,
        );

        if ($this->isKnownToHomeAssistant($exposedThreshold) && $exposedThreshold->getValue() === null) {
            $exposedThreshold->setValue($initialThreshold);
        }

        return $exposedThreshold;
    }

    // Until Home Assistant creates the entity its restored value is unknown, so seeding could overwrite a tuned one.
    private function isKnownToHomeAssistant(ExposedEntity $entity): bool
    {
        return $entity->getEntityId() !== null;
    }

    private function composeKey(string ...$parts): string
    {
        return implode('_', [$this->transitionName, ...$parts]);
    }

    private function composeName(string $subject): string
    {
        return ucfirst(str_replace('_', ' ', "{$this->transitionName}_{$subject}"));
    }
}
