<?php

declare(strict_types=1);

namespace Shared\HouseMode;

use Closure;
use Psr\Log\LoggerInterface;
use Stewart\Contracts\Exception\ExposureException;

final readonly class TransitionMonitor
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    /**
     * @param Closure(TransitionFiring): void $whenFired
     * @throws ExposureException
     */
    public function startWatching(
        ModeTransition $transition,
        ModeContext $context,
        Closure $whenFired,
    ): WatchedTransition {
        $watched = new WatchedTransition(
            $transition,
            new TransitionContext($context, $transition->name),
            $whenFired,
            $this->logger,
        );
        $watched->startWatching();

        return $watched;
    }
}
