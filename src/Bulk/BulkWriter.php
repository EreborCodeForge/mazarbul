<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Bulk;

use EreborCodeForge\Mazarbul\Connection\ManagedConnection;
use EreborCodeForge\Mazarbul\Contract\Observer;
use EreborCodeForge\Mazarbul\Exception\BulkException;
use EreborCodeForge\Mazarbul\Observability\Event\BulkFinished;
use EreborCodeForge\Mazarbul\Observability\Event\BulkStarted;
use EreborCodeForge\Mazarbul\Observability\Event\ChunkProcessed;
use EreborCodeForge\Mazarbul\Observability\Event\RetryAttempted;
use EreborCodeForge\Mazarbul\Query\PdoQueryExecutor;
use EreborCodeForge\Mazarbul\Support\Clock;
use EreborCodeForge\Mazarbul\Support\NativeSleeper;
use EreborCodeForge\Mazarbul\Support\Sleeper;
use EreborCodeForge\Mazarbul\Support\SystemClock;
use Throwable;

final class BulkWriter
{
    private readonly BulkInsert $insertBuilder;
    private readonly BulkDelete $deleteBuilder;

    /** @var (callable(string, list<mixed>): void)|null */
    private $faultInjector = null;

    public function __construct(
        private readonly ManagedConnection $connection,
        private readonly PdoQueryExecutor $executor,
        private readonly Observer $observer,
        private readonly Clock $clock = new SystemClock(),
        private readonly Sleeper $sleeper = new NativeSleeper(),
    ) {
        $this->insertBuilder = new BulkInsert();
        $this->deleteBuilder = new BulkDelete();
    }

    /**
     * Test-only hook. Not part of the stable public API.
     *
     * @param callable(string, list<mixed>): void $injector
     */
    public function setFaultInjector(callable $injector): void
    {
        $this->faultInjector = $injector;
    }

    /**
     * @param list<string> $columns
     * @param iterable<array<int|string, mixed>> $rows
     */
    #[\NoDiscard]
    public function insert(
        string $table,
        array $columns,
        iterable $rows,
        BulkOptions $options = new BulkOptions(),
    ): int {
        if ($columns === []) {
            throw BulkException::invalidColumns();
        }

        $dialect = $this->connection->driverObject()->dialect;
        foreach ($columns as $column) {
            $dialect->assertValidIdentifier($column);
        }
        $dialect->assertValidIdentifier($table);

        $maxRows = $this->effectiveChunkSize(
            $options->chunkSize,
            count($columns),
        );

        return $this->runChunked(
            operation: 'insert',
            table: $table,
            rows: $rows,
            chunkSize: $maxRows,
            options: $options,
            executeChunk: function (array $chunk) use ($table, $columns, $dialect): int {
                /** @var list<array<int|string, mixed>> $rows */
                $rows = $chunk;
                $built = $this->insertBuilder->build($dialect, $table, $columns, $rows);
                $this->maybeInjectFault('insert', $built['params']);
                $result = $this->executor->execute($built['sql'], $built['params']);

                return $result->rowCount > 0 ? $result->rowCount : count($chunk);
            },
        );
    }

    /**
     * @param list<string> $updateColumns
     * @param iterable<array<string, mixed>> $rows
     */
    #[\NoDiscard]
    public function update(
        string $table,
        string $keyColumn,
        array $updateColumns,
        iterable $rows,
        BulkOptions $options = new BulkOptions(),
    ): int {
        if ($updateColumns === []) {
            throw BulkException::invalidColumns();
        }

        $driver = $this->connection->driverObject();
        $dialect = $driver->dialect;
        $dialect->assertValidIdentifier($table);
        $dialect->assertValidIdentifier($keyColumn);
        foreach ($updateColumns as $column) {
            $dialect->assertValidIdentifier($column);
        }

        $paramsPerRow = 1 + (count($updateColumns) * 2) + 1; // CASE/WHEN style upper bound
        $maxRows = $this->effectiveChunkSize($options->chunkSize, $paramsPerRow);
        $updater = new BulkUpdate($driver->bulkUpdateStrategy);

        return $this->runChunked(
            operation: 'update',
            table: $table,
            rows: $rows,
            chunkSize: $maxRows,
            options: $options,
            executeChunk: function (array $chunk) use ($updater, $dialect, $table, $keyColumn, $updateColumns): int {
                /** @var list<array<string, mixed>> $chunk */
                $built = $updater->build($dialect, $table, $keyColumn, $updateColumns, $chunk);
                $this->maybeInjectFault('update', $built['params']);
                $result = $this->executor->execute($built['sql'], $built['params']);

                return $result->rowCount > 0 ? $result->rowCount : count($chunk);
            },
        );
    }

    /**
     * @param iterable<mixed> $ids
     */
    #[\NoDiscard]
    public function delete(
        string $table,
        string $keyColumn,
        iterable $ids,
        BulkOptions $options = new BulkOptions(),
    ): int {
        $dialect = $this->connection->driverObject()->dialect;
        $dialect->assertValidIdentifier($table);
        $dialect->assertValidIdentifier($keyColumn);

        $maxRows = $this->effectiveChunkSize($options->chunkSize, 1);

        return $this->runChunked(
            operation: 'delete',
            table: $table,
            rows: $ids,
            chunkSize: $maxRows,
            options: $options,
            executeChunk: function (array $chunk) use ($table, $keyColumn, $dialect): int {
                $built = $this->deleteBuilder->build($dialect, $table, $keyColumn, $chunk);
                $this->maybeInjectFault('delete', $built['params']);
                $result = $this->executor->execute($built['sql'], $built['params']);

                return $result->rowCount > 0 ? $result->rowCount : count($chunk);
            },
        );
    }

    /**
     * @param iterable<mixed> $rows
     * @param callable(list<mixed>): int $executeChunk
     */
    private function runChunked(
        string $operation,
        string $table,
        iterable $rows,
        int $chunkSize,
        BulkOptions $options,
        callable $executeChunk,
    ): int {
        $started = $this->clock->now();
        if (!$this->observer->isNoop()) {
            $this->observer->notify(new BulkStarted(
                connectionName: $this->connection->name(),
                operation: $operation,
                table: $table,
            ));
        }

        $affected = 0;
        $chunkIndex = 0;
        $buffer = [];

        $beginAll = $options->transactionMode === TransactionMode::ALL;
        if ($beginAll) {
            $this->connection->beginTransaction();
        }

        try {
            foreach ($rows as $row) {
                $buffer[] = $row;
                if (count($buffer) >= $chunkSize) {
                    $affected += $this->executeChunkWithRetry(
                        operation: $operation,
                        chunkIndex: $chunkIndex,
                        chunk: $buffer,
                        options: $options,
                        executeChunk: $executeChunk,
                    );
                    ++$chunkIndex;
                    $buffer = [];
                }
            }

            if ($buffer !== []) {
                $affected += $this->executeChunkWithRetry(
                    operation: $operation,
                    chunkIndex: $chunkIndex,
                    chunk: $buffer,
                    options: $options,
                    executeChunk: $executeChunk,
                );
            }

            if ($beginAll) {
                $this->connection->commit();
            }
        } catch (Throwable $e) {
            if ($beginAll && $this->connection->isInTransaction()) {
                try {
                    $this->connection->rollBack();
                } catch (Throwable) {
                }
            }

            throw BulkException::operationFailed($operation, $e);
        }

        if (!$this->observer->isNoop()) {
            $this->observer->notify(new BulkFinished(
                connectionName: $this->connection->name(),
                operation: $operation,
                table: $table,
                affectedRows: $affected,
                durationSeconds: $this->clock->now() - $started,
            ));
        }

        return $affected;
    }

    /**
     * @param list<mixed> $chunk
     * @param callable(list<mixed>): int $executeChunk
     */
    private function executeChunkWithRetry(
        string $operation,
        int $chunkIndex,
        array $chunk,
        BulkOptions $options,
        callable $executeChunk,
    ): int {
        $policy = $options->retryPolicy;
        $attempt = 1;
        $lastError = null;

        while ($attempt <= $policy->maxAttempts) {
            $ownsChunkTx = $options->transactionMode === TransactionMode::PER_CHUNK;
            if ($ownsChunkTx) {
                $this->connection->beginTransaction();
            }

            try {
                $count = $executeChunk($chunk);
                if ($ownsChunkTx) {
                    $this->connection->commit();
                }

                if (!$this->observer->isNoop()) {
                    $this->observer->notify(new ChunkProcessed(
                        connectionName: $this->connection->name(),
                        operation: $operation,
                        chunkIndex: $chunkIndex,
                        rowCount: count($chunk),
                    ));
                }

                return $count;
            } catch (Throwable $e) {
                $lastError = $e;
                if ($ownsChunkTx && $this->connection->isInTransaction()) {
                    try {
                        $this->connection->rollBack();
                    } catch (Throwable) {
                    }
                } elseif ($this->connection->isInTransaction() && $options->transactionMode === TransactionMode::NONE) {
                    // keep outer state intact
                }

                $canRetry = $attempt < $policy->maxAttempts
                    && $policy->decider->isRetryable($e)
                    && (
                        $options->transactionMode === TransactionMode::PER_CHUNK
                        || $policy->allowRetryWithoutTransaction
                    );

                if (!$canRetry) {
                    throw $e;
                }

                $delay = $policy->backoff->delaySeconds($attempt);
                if (!$this->observer->isNoop()) {
                    $this->observer->notify(new RetryAttempted(
                        connectionName: $this->connection->name(),
                        operation: $operation,
                        attempt: $attempt,
                        maxAttempts: $policy->maxAttempts,
                        delaySeconds: $delay,
                        errorClass: $e::class,
                    ));
                }
                $this->sleeper->sleep($delay);
                ++$attempt;
            }
        }

        throw BulkException::retriesExhausted(
            $operation,
            $policy->maxAttempts,
            $lastError ?? new BulkException('Unknown bulk failure.'),
        );
    }

    private function effectiveChunkSize(int $requested, int $paramsPerRow): int
    {
        $maxBind = $this->connection->driverObject()->capabilities->maxBindParameters;
        $byDriver = max(1, intdiv($maxBind, max(1, $paramsPerRow)));

        return min($requested, $byDriver);
    }

    /**
     * @param list<mixed> $params
     */
    private function maybeInjectFault(string $operation, array $params): void
    {
        if ($this->faultInjector !== null) {
            ($this->faultInjector)($operation, $params);
        }
    }
}
