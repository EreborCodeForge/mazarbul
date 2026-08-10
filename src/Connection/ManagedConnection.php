<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Connection;

use EreborCodeForge\Mazarbul\Contract\Connection;
use EreborCodeForge\Mazarbul\Contract\ConnectionFactory;
use EreborCodeForge\Mazarbul\Contract\Observer;
use EreborCodeForge\Mazarbul\Driver\Driver;
use EreborCodeForge\Mazarbul\Exception\ConnectionException;
use EreborCodeForge\Mazarbul\Exception\TransactionException;
use EreborCodeForge\Mazarbul\Observability\Event\ConnectionClosed;
use EreborCodeForge\Mazarbul\Observability\Event\ConnectionOpened;
use EreborCodeForge\Mazarbul\Observability\Event\ConnectionReconnected;
use EreborCodeForge\Mazarbul\Observability\Event\ConnectionReused;
use EreborCodeForge\Mazarbul\Observability\Event\TransactionCommitted;
use EreborCodeForge\Mazarbul\Observability\Event\TransactionRolledBack;
use EreborCodeForge\Mazarbul\Observability\Event\TransactionStarted;
use EreborCodeForge\Mazarbul\Support\Clock;
use EreborCodeForge\Mazarbul\Support\SystemClock;
use PDO;
use Throwable;

final class ManagedConnection implements Connection
{
    private ?PDO $pdo = null;
    private ConnectionState $state = ConnectionState::Configured;
    private int $generation = 0;
    private ?float $openedAt = null;
    private ?float $lastUsedAt = null;
    private ?float $lastHealthCheckAt = null;
    private bool $inTransaction = false;
    private int $activeStreams = 0;

    public function __construct(
        private readonly ConnectionDefinition $definition,
        private readonly ConnectionFactory $factory,
        private readonly Driver $driver,
        private readonly Observer $observer,
        private readonly Clock $clock = new SystemClock(),
    ) {
        $this->state = ConnectionState::NotConnected;
    }

    public function name(): string
    {
        return $this->definition->name;
    }

    public function driver(): string
    {
        return $this->driver->name;
    }

    public function driverObject(): Driver
    {
        return $this->driver;
    }

    public function definition(): ConnectionDefinition
    {
        return $this->definition;
    }

    public function state(): ConnectionState
    {
        return $this->state;
    }

    public function generation(): int
    {
        return $this->generation;
    }

    public function isConnected(): bool
    {
        return $this->pdo !== null && $this->state === ConnectionState::Connected;
    }

    public function isInTransaction(): bool
    {
        return $this->inTransaction;
    }

    public function hasActiveStreams(): bool
    {
        return $this->activeStreams > 0;
    }

    public function registerStreamOpen(): void
    {
        ++$this->activeStreams;
    }

    public function registerStreamClose(): void
    {
        if ($this->activeStreams > 0) {
            --$this->activeStreams;
        }
    }

    public function pdo(): PDO
    {
        $this->ensureConnected();
        assert($this->pdo instanceof PDO);

        return $this->pdo;
    }

    public function ensureConnected(): void
    {
        if ($this->pdo !== null) {
            $this->maybeRotateOrHealthCheck();
            if ($this->pdo !== null) {
                $this->touch();
                $this->observer->notify(new ConnectionReused(
                    connectionName: $this->name(),
                    driver: $this->driver(),
                    generation: $this->generation,
                ));

                return;
            }
        }

        $this->open(false);
    }

    public function open(bool $reconnect): void
    {
        $pdo = $this->factory->create($this->definition->config);
        $this->pdo = $pdo;
        $this->state = ConnectionState::Connected;
        ++$this->generation;
        $now = $this->clock->now();
        $this->openedAt = $now;
        $this->lastUsedAt = $now;
        $this->lastHealthCheckAt = $now;
        $this->inTransaction = false;

        if ($reconnect) {
            $this->observer->notify(new ConnectionReconnected(
                connectionName: $this->name(),
                driver: $this->driver(),
                generation: $this->generation,
            ));
        } else {
            $this->observer->notify(new ConnectionOpened(
                connectionName: $this->name(),
                driver: $this->driver(),
                generation: $this->generation,
            ));
        }
    }

    public function close(string $reason = 'manual'): void
    {
        if ($this->pdo === null) {
            $this->state = ConnectionState::Closed;

            return;
        }

        if ($this->inTransaction) {
            try {
                $this->pdo->rollBack();
            } catch (Throwable) {
                // best effort
            }
            $this->inTransaction = false;
        }

        $generation = $this->generation;
        $this->pdo = null;
        $this->state = ConnectionState::Closed;
        $this->openedAt = null;
        $this->lastUsedAt = null;
        $this->lastHealthCheckAt = null;
        $this->activeStreams = 0;

        $this->observer->notify(new ConnectionClosed(
            connectionName: $this->name(),
            driver: $this->driver(),
            generation: $generation,
            reason: $reason,
        ));
    }

    public function beginTransaction(): void
    {
        $pdo = $this->pdo();
        if ($this->inTransaction || $pdo->inTransaction()) {
            throw TransactionException::nestedNotSupported();
        }

        try {
            $pdo->beginTransaction();
        } catch (Throwable $e) {
            throw TransactionException::beginFailed($e);
        }

        $this->inTransaction = true;
        $this->observer->notify(new TransactionStarted($this->name()));
    }

    public function commit(): void
    {
        $pdo = $this->pdo();
        try {
            $pdo->commit();
        } catch (Throwable $e) {
            throw TransactionException::commitFailed($e);
        }

        $this->inTransaction = false;
        $this->observer->notify(new TransactionCommitted($this->name()));
    }

    public function rollBack(): void
    {
        $pdo = $this->pdo();
        try {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        } catch (Throwable $e) {
            throw TransactionException::rollbackFailed($e);
        }

        $this->inTransaction = false;
        $this->observer->notify(new TransactionRolledBack($this->name(), 'explicit'));
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->pdo()->lastInsertId($name);
    }

    public function ping(): bool
    {
        if ($this->pdo === null) {
            return false;
        }

        try {
            $this->pdo->query('SELECT 1');
            $this->lastHealthCheckAt = $this->clock->now();

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function health(): ConnectionHealth
    {
        $alive = $this->ping();

        return new ConnectionHealth(
            alive: $alive,
            checkedAt: $this->lastHealthCheckAt,
            message: $alive ? null : 'Connection health check failed.',
        );
    }

    public function onRequestEnd(): void
    {
        if ($this->pdo !== null && ($this->inTransaction || $this->pdo->inTransaction())) {
            try {
                $this->pdo->rollBack();
            } catch (Throwable) {
                // best effort cleanup
            }
            $this->inTransaction = false;
            $this->observer->notify(new TransactionRolledBack($this->name(), 'request_end'));
        }

        $this->activeStreams = 0;

        $policy = $this->definition->config->lifecycle;
        if ($this->shouldCloseForIdle($policy) || $this->shouldCloseForLifetime($policy)) {
            $this->close($this->shouldCloseForLifetime($policy) ? 'max_lifetime' : 'idle_timeout');
            $this->state = ConnectionState::NotConnected;
        }
    }

    public function onWorkerStop(): void
    {
        $this->close('worker_stop');
    }

    private function maybeRotateOrHealthCheck(): void
    {
        $policy = $this->definition->config->lifecycle;

        if ($this->shouldCloseForLifetime($policy)) {
            $this->close('max_lifetime');
            $this->state = ConnectionState::NotConnected;
            $this->open(true);

            return;
        }

        if ($this->shouldCloseForIdle($policy)) {
            $this->close('idle_timeout');
            $this->state = ConnectionState::NotConnected;
            $this->open(true);

            return;
        }

        if ($this->shouldHealthCheck($policy) && !$this->ping()) {
            if (!$policy->reconnectOnFailure) {
                throw ConnectionException::notConnected($this->name());
            }
            $this->close('health_check_failed');
            $this->state = ConnectionState::NotConnected;
            $this->open(true);
        }
    }

    private function shouldCloseForLifetime(LifecyclePolicy $policy): bool
    {
        return $policy->maxLifetimeSeconds !== null
            && $this->openedAt !== null
            && ($this->clock->now() - $this->openedAt) >= $policy->maxLifetimeSeconds;
    }

    private function shouldCloseForIdle(LifecyclePolicy $policy): bool
    {
        return $policy->idleTimeoutSeconds !== null
            && $this->lastUsedAt !== null
            && ($this->clock->now() - $this->lastUsedAt) >= $policy->idleTimeoutSeconds;
    }

    private function shouldHealthCheck(LifecyclePolicy $policy): bool
    {
        return $policy->healthCheckIntervalSeconds !== null
            && $this->lastHealthCheckAt !== null
            && ($this->clock->now() - $this->lastHealthCheckAt) >= $policy->healthCheckIntervalSeconds;
    }

    private function touch(): void
    {
        $this->lastUsedAt = $this->clock->now();
    }
}
