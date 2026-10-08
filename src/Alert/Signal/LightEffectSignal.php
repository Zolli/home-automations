<?php

declare(strict_types=1);

namespace Shared\Alert\Signal;

use Shared\Alert\FaultLocator;
use Shared\Alert\Rule\Fault;
use Stewart\Contracts\HaContext;
use Stewart\Contracts\Registry\EntityFilter;
use Stewart\Contracts\Service\ServiceTarget;
use Stewart\Contracts\State\EntityState;

final readonly class LightEffectSignal implements Signal
{
    private const string LIGHT_DOMAIN = 'light';
    private const string EFFECT_LIST_ATTRIBUTE = 'effect_list';

    public function __construct(
        private string $effect,
        private FaultLocator $locator,
    ) {}

    public function emit(Fault $fault, HaContext $ha): void
    {
        $area = $this->locator->findArea($fault, $ha);

        if ($area === null) {
            return;
        }

        $lightIds = $ha->listStates(EntityFilter::inArea($area->areaId)->withDomain(self::LIGHT_DOMAIN))
            ->filter(fn(EntityState $light): bool => \in_array($this->effect, $light->getArrayAttribute(self::EFFECT_LIST_ATTRIBUTE) ?? [], true))
            ->mapToList(static fn(EntityState $light) => $light->entityId);

        if ($lightIds !== []) {
            $ha->callService(self::LIGHT_DOMAIN, 'turn_on', ['effect' => $this->effect], ServiceTarget::forEntities(...$lightIds));
        }
    }

    public function describe(): string
    {
        return "light effect {$this->effect} in the fault area";
    }
}
