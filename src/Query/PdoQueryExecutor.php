<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Query;

use EreborCodeForge\Mazarbul\Connection\ManagedConnection;
use EreborCodeForge\Mazarbul\Contract\Observer;
use EreborCodeForge\Mazarbul\Contract\QueryExecutor;
use EreborCodeForge\Mazarbul\Exception\QueryException;
use EreborCodeForge\Mazarbul\Observability\Event\QueryExecuted;
use EreborCodeForge\Mazarbul\Observability\Event\QueryFailed;
use EreborCodeForge\Mazarbul\Support\Clock;
use EreborCodeForge\Mazarbul\Support\SystemClock;
use PDO;
use PDOStatement;
use Throwable;

final class PdoQueryExecutor implements QueryExecutor
{
    private const STATEMENT_CACHE_LIMIT = 32;

    /** @var array<string, PDOStatement> */
    private array $statementCache = [];

    private int $cacheGeneration = -1;

    public function __construct(
        private readonly ManagedConnection $connection,
        private readonly Observer $observer,
        private readonly Clock $clock = new SystemClock(),
    ) {
    }

    public function execute(string $sql, array $params = []): StatementResult
    {
        $started = $this->clock->now();
        try {
            $statement = $this->prepareAndExecute($sql, $params);
            $count = $statement->rowCount();
            $statement->closeCursor();
            $this->notifySuccess('execute', $started, $count);

            return new StatementResult($count);
        } catch (Throwable $e) {
            $this->notifyFailure('execute', $e);
            throw QueryException::executionFailed($sql, $e);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $started = $this->clock->now();
        try {
            $statement = $this->prepareAndExecute($sql, $params);
            $row = $statement->fetch(PDO::FETCH_ASSOC);
            $statement->closeCursor();
            $this->notifySuccess('fetchOne', $started, $row === false ? 0 : 1);

            if ($row === false) {
                return null;
            }

            /** @var array<string, mixed> $row */
            return $row;
        } catch (Throwable $e) {
            $this->notifyFailure('fetchOne', $e);
            throw QueryException::executionFailed($sql, $e);
        }
    }

    public function fetchAll(string $sql, array $params = []): array
    {
        $started = $this->clock->now();
        try {
            $statement = $this->prepareAndExecute($sql, $params);
            /** @var list<array<string, mixed>> $rows */
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
            $statement->closeCursor();
            $this->notifySuccess('fetchAll', $started, count($rows));

            return $rows;
        } catch (Throwable $e) {
            $this->notifyFailure('fetchAll', $e);
            throw QueryException::executionFailed($sql, $e);
        }
    }

    public function scalar(string $sql, array $params = []): mixed
    {
        $row = $this->fetchOne($sql, $params);
        if ($row === null) {
            return null;
        }

        return array_first($row);
    }

    public function query(string $sql, array $params = []): QueryResult
    {
        $started = $this->clock->now();
        try {
            $statement = $this->prepareAndExecute($sql, $params);
            $this->notifySuccess('query', $started, null);

            return new QueryResult($statement);
        } catch (Throwable $e) {
            $this->notifyFailure('query', $e);
            throw QueryException::executionFailed($sql, $e);
        }
    }

    /**
     * @param list<mixed>|array<string, mixed> $params
     */
    public function prepareAndExecute(string $sql, array $params = []): PDOStatement
    {
        $pdo = $this->connection->pdo();
        $generation = $this->connection->generation();
        if ($generation !== $this->cacheGeneration) {
            $this->statementCache = [];
            $this->cacheGeneration = $generation;
        }

        $statement = $this->statementCache[$sql] ?? null;
        if ($statement === null) {
            $statement = $pdo->prepare($sql);
            if (count($this->statementCache) < self::STATEMENT_CACHE_LIMIT) {
                $this->statementCache[$sql] = $statement;
            }
        }

        $statement->execute($params);

        return $statement;
    }

    private function notifySuccess(string $operation, float $started, ?int $rowCount): void
    {
        if ($this->observer->isNoop()) {
            return;
        }

        $this->observer->notify(new QueryExecuted(
            connectionName: $this->connection->name(),
            driver: $this->connection->driver(),
            operation: $operation,
            durationSeconds: $this->clock->now() - $started,
            rowCount: $rowCount,
        ));
    }

    private function notifyFailure(string $operation, Throwable $e): void
    {
        if ($this->observer->isNoop()) {
            return;
        }

        $this->observer->notify(new QueryFailed(
            connectionName: $this->connection->name(),
            driver: $this->connection->driver(),
            operation: $operation,
            errorClass: $e::class,
            message: $e->getMessage(),
        ));
    }
}
