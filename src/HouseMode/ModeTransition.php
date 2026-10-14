<?php

declare(strict_types=1);

namespace Shared\HouseMode;

use Closure;
use Shared\HouseMode\Collection\ModeTriggerCollection;
use Shared\HouseMode\Trigger\ModeTrigger;
use Stewart\Contracts\Exception\CommandException;
use Stewart\Contracts\Exception\ExposureException;
use Stewart\Contracts\Exposure\Command\SelectCommand;
use Stewart\Contracts\Subscription;

final class ModeTransition
{
    private ?ModeTrigger $selectedTrigger = null;

    private ?Subscription $selectCommands = null;

    public function __construct(
        public readonly string $name,
        public readonly ModeTriggerCollection $strategies,
        public readonly string $defaultStrategyName,
        public readonly ModeTriggerCollection $alwaysActive,
        public readonly ?TransitionWindow $window = null,
    ) {}

    /**
     * @param Closure(string): void $whenFired
     * @throws ExposureException
     */
    public function startWatching(ModeContext $modeContext, Closure $whenFired): void
    {
        $context = new TransitionContext($modeContext, $this->name);
        $fire    = fn(string $strategyName) => $this->fireStrategy($context, $whenFired, $strategyName);

        $this->strategies->attachAll($context);
        $this->alwaysActive->attachAll($context);
        $this->alwaysActive->armAll($context, $fire);

        $select = $context->exposeStrategySelect($this->strategies->listNames(), $this->defaultStrategyName);
        $this->selectStrategy($context, $fire, $this->resolveStrategyName($select->getOption()));
        $this->selectCommands = $select->watchCommands()->subscribe(
            fn(SelectCommand $command) => $this->switchStrategy($context, $fire, $command),
        );
    }

    public function stopWatching(): void
    {
        $this->selectCommands?->unsubscribe();
        $this->selectCommands  = null;
        $this->selectedTrigger = null;
        $this->strategies->detachAll();
        $this->alwaysActive->detachAll();
    }

    private function resolveStrategyName(?string $selectedName): string
    {
        return $selectedName !== null && $this->strategies->find($selectedName) !== null
            ? $selectedName
            : $this->defaultStrategyName;
    }

    /**
     * @param Closure(string): void $fire
     * @throws CommandException
     */
    private function switchStrategy(TransitionContext $context, Closure $fire, SelectCommand $command): void
    {
        if ($this->strategies->find($command->option) === null) {
            throw CommandException::rejected("Strategy \"{$command->option}\" is not configured for {$this->name}.");
        }

        $this->selectStrategy($context, $fire, $command->option);
    }

    /** @param Closure(string): void $fire */
    private function selectStrategy(TransitionContext $context, Closure $fire, string $strategyName): void
    {
        $this->selectedTrigger?->disarm();
        $this->selectedTrigger = $this->strategies->find($strategyName);
        $this->selectedTrigger?->arm($context, $fire);

        $context->mode->logger->info(
            "[MODE] {$this->name} uses {$strategyName}: {$this->selectedTrigger?->describe()}",
        );
    }

    /** @param Closure(string): void $whenFired */
    private function fireStrategy(TransitionContext $context, Closure $whenFired, string $strategyName): void
    {
        if ($this->window !== null && !$this->window->isOpen($context->mode->clock)) {
            $context->mode->logger->info(
                "[MODE] {$this->name} by {$strategyName} skipped outside {$this->window->describe()}",
            );

            return;
        }

        $context->mode->logger->info("[MODE] {$this->name} fired by {$strategyName}");
        $whenFired($strategyName);
    }
}
