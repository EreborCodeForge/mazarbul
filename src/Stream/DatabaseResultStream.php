<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Stream;

use EreborCodeForge\Mazarbul\Connection\ManagedConnection;
use EreborCodeForge\Mazarbul\Contract\Observer;
use EreborCodeForge\Mazarbul\Contract\ResultStream;
use EreborCodeForge\Mazarbul\Driver\PostgreSql\PostgreSqlCursorReader;
use EreborCodeForge\Mazarbul\Exception\QueryException;
use EreborCodeForge\Mazarbul\Exception\StreamConsumedException;
use EreborCodeForge\Mazarbul\Observability\Event\StreamFinished;
use EreborCodeForge\Mazarbul\Observability\Event\StreamStarted;
use EreborCodeForge\Mazarbul\Pipeline\Pipeline;
use EreborCodeForge\Mazarbul\Support\Clock;
use EreborCodeForge\Mazarbul\Support\SystemClock;
use PDO;
use PDOStatement;
use Throwable;
use Traversable;

/**
 * Single-pass database cursor stream.
 */
final class DatabaseResultStream implements ResultStream
{
    private StreamState $state = StreamState::Ready;
    private ?PDOStatement $statement = null;
    private bool $streamingConfigured = false;

    /**
     * @param list<mixed>|array<string, mixed> $params
     */
    public function __construct(
        private readonly ManagedConnection $connection,
        private readonly string $sql,
        private readonly array $params,
        private readonly Observer $observer,
        private readonly Clock $clock = new SystemClock(),
    ) {
    }

    public function close(): void
    {
        $this->cleanup();
        $this->state = StreamState::Closed;
    }

    public function isConsumed(): bool
    {
        return $this->state === StreamState::Consumed || $this->state === StreamState::Closed;
    }

    public function pipeline(): Pipeline
    {
        return Pipeline::from($this);
    }

    public function getIterator(): Traversable
    {
        if ($this->state === StreamState::Consumed || $this->state === StreamState::Closed || $this->state === StreamState::Open) {
            throw StreamConsumedException::create();
        }

        $this->state = StreamState::Open;
        $this->connection->registerStreamOpen();
        if (!$this->observer->isNoop()) {
            $this->observer->notify(new StreamStarted(
                connectionName: $this->connection->name(),
                driver: $this->connection->driver(),
            ));
        }

        $started = $this->clock->now();
        $rows = 0;

        try {
            $driver = $this->connection->driverObject();
            if ($driver->capabilities->supportsServerSideCursor) {
                $reader = new PostgreSqlCursorReader($this->connection, $this->sql, $this->params);
                foreach ($reader->rows() as $row) {
                    ++$rows;
                    yield $row;
                }

                return;
            }

            $pdo = $this->connection->pdo();
            $driver->configureStreaming($pdo);
            $this->streamingConfigured = true;

            try {
                $this->statement = $pdo->prepare($this->sql);
                $this->statement->execute($this->params);
            } catch (Throwable $e) {
                throw QueryException::executionFailed($this->sql, $e);
            }

            while (true) {
                $row = $this->statement->fetch(PDO::FETCH_ASSOC);
                if ($row === false) {
                    break;
                }
                ++$rows;
                yield $row;
            }
        } catch (Throwable $e) {
            if ($e instanceof QueryException) {
                throw $e;
            }
            throw QueryException::executionFailed($this->sql, $e);
        } finally {
            $this->cleanup();
            $this->state = StreamState::Consumed;
            $this->connection->registerStreamClose();
            if (!$this->observer->isNoop()) {
                $this->observer->notify(new StreamFinished(
                    connectionName: $this->connection->name(),
                    driver: $this->connection->driver(),
                    rowsStreamed: $rows,
                    durationSeconds: $this->clock->now() - $started,
                ));
            }
        }
    }

    private function cleanup(): void
    {
        if ($this->statement !== null) {
            try {
                $this->statement->closeCursor();
            } catch (Throwable) {
                // best effort
            }
            $this->statement = null;
        }

        if ($this->streamingConfigured) {
            try {
                $this->connection->driverObject()->restoreStreaming($this->connection->pdo());
            } catch (Throwable) {
                // connection may already be closed
            }
            $this->streamingConfigured = false;
        }
    }
}
