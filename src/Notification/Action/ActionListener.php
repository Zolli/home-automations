<?php

declare(strict_types=1);

namespace Shared\Notification\Action;

use Closure;
use Psr\Log\LoggerInterface;
use Shared\Notification\NotificationId;
use Throwable;

final class ActionListener
{
    /** @var array<string, list<Closure(NotificationAction): void>> */
    private array $callbacks = [];

    /** @var list<Closure(NotificationAction): void> */
    private array $callbacksForAnyAction = [];

    /** @var list<Closure(NotificationDismissal): void> */
    private array $callbacksForDismissal = [];

    /** @param Closure(self): void $release */
    public function __construct(
        public readonly NotificationId $notificationId,
        private readonly LoggerInterface $logger,
        private readonly Closure $release,
        private bool $listening = true,
    ) {}

    /** @param Closure(NotificationAction): void $callback */
    public function onAction(string $action, Closure $callback): self
    {
        if ($this->listening) {
            $this->callbacks[$action][] = $callback;
        }

        return $this;
    }

    /** @param Closure(NotificationAction): void $callback */
    public function onAnyAction(Closure $callback): self
    {
        if ($this->listening) {
            $this->callbacksForAnyAction[] = $callback;
        }

        return $this;
    }

    /** @param Closure(NotificationDismissal): void $callback */
    public function onDismissed(Closure $callback): self
    {
        if ($this->listening) {
            $this->callbacksForDismissal[] = $callback;
        }

        return $this;
    }

    public function isListening(): bool
    {
        return $this->listening;
    }

    public function stopListening(): void
    {
        if (!$this->listening) {
            return;
        }

        $this->listening = false;
        $this->callbacks = [];
        $this->callbacksForAnyAction = [];
        $this->callbacksForDismissal = [];
        ($this->release)($this);
    }

    public function dispatchAction(NotificationAction $action): void
    {
        foreach ([...$this->callbacks[$action->action] ?? [], ...$this->callbacksForAnyAction] as $callback) {
            $this->runCallback(static fn() => $callback($action), ['action' => $action->action]);
        }
    }

    public function dispatchDismissal(NotificationDismissal $dismissal): void
    {
        foreach ($this->callbacksForDismissal as $callback) {
            $this->runCallback(static fn() => $callback($dismissal), ['action' => 'dismissed']);
        }
    }

    /** @param array<string, mixed> $context */
    private function runCallback(Closure $callback, array $context): void
    {
        try {
            $callback();
        } catch (Throwable $e) {
            $this->logger->error('[NOTIFICATION] Action callback failed', ['id' => $this->notificationId->value, ...$context, 'exception' => $e]);
        }
    }
}
