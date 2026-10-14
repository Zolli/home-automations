<?php

declare(strict_types=1);

namespace Shared\HouseMode;

use Closure;
use Psr\Log\LoggerInterface;
use Shared\HouseMode\Strategy\Collection\StrategyTriggerCollection;
use Shared\HouseMode\Strategy\StrategyTrigger;
use Stewart\Contracts\Exception\CommandException;
use Stewart\Contracts\Exception\ExposureException;
use Stewart\Contracts\Exposure\Command\SelectCommand;
use Stewart\Contracts\Subscription;

final class WatchedTransition
{
    private StrategyTriggerCollection $selectableTriggers;

    private StrategyTriggerCollection $alwaysActiveTriggers;

    private ?StrategyTrigger $selectedTrigger = null;

    private ?Subscription $selectCommands = null;

    /** @param Closure(TransitionFiring): void $whenFired */
    public function __construct(
        private readonly ModeTransition $transition,
        private readonly TransitionContext $context,
        private readonly Closure $whenFired,
        private readonly LoggerInterface $logger,
    ) {
        $this->selectableTriggers   = StrategyTriggerCollection::empty();
        $this->alwaysActiveTriggers = StrategyTriggerCollection::empty();
    }

    /** @throws ExposureException */
    public function startWatching(): void
    {
        $this->selectableTriggers   = $this->transition->strategies->attachAll($this->context);
        $this->alwaysActiveTriggers = $this->transition->alwaysActive->attachAll($this->context);
        $this->alwaysActiveTriggers->armAll($this->fireStrategy(...));

        $select = $this->context->exposeStrategySelect(
            $this->transition->strategies->listNames(),
            $this->transition->defaultStrategyName,
        );
        $this->selectStrategy($this->transition->resolveStrategyName($select->getOption()));
        $this->selectCommands = $select->watchCommands()->subscribe($this->switchStrategy(...));
    }

    public function stopWatching(): void
    {
        $this->selectCommands?->unsubscribe();
        $this->selectCommands  = null;
        $this->selectedTrigger = null;
        $this->selectableTriggers->detachAll();
        $this->alwaysActiveTriggers->detachAll();
    }

    /** @throws CommandException */
    private function switchStrategy(SelectCommand $command): void
    {
        if ($this->selectableTriggers->find($command->option) === null) {
            throw CommandException::rejected(
                "Strategy \"{$command->option}\" is not configured for {$this->transition->name}.",
            );
        }

        $this->selectStrategy($command->option);
    }

    private function selectStrategy(string $strategyName): void
    {
        $this->selectedTrigger?->disarm();
        $this->selectedTrigger = $this->selectableTriggers->find($strategyName);
        $this->selectedTrigger?->arm($this->fireStrategy(...));

        $this->logger->info(
            "[MODE] {$this->transition->name} uses {$strategyName}: {$this->selectedTrigger?->describe()}",
        );
    }

    private function fireStrategy(string $strategyName): void
    {
        $window = $this->transition->window;

        if ($window !== null && !$window->isOpen($this->context->mode->clock)) {
            $this->logger->info(
                "[MODE] {$this->transition->name} by {$strategyName} skipped outside {$window->describe()}",
            );

            return;
        }

        $this->logger->info("[MODE] {$this->transition->name} fired by {$strategyName}");
        ($this->whenFired)(new TransitionFiring($this->transition->name, $strategyName));
    }
}
