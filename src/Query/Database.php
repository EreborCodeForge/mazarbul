<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Query;

use EreborCodeForge\Mazarbul\Bulk\BulkWriter;
use EreborCodeForge\Mazarbul\Connection\ManagedConnection;
use EreborCodeForge\Mazarbul\Contract\Observer;
use EreborCodeForge\Mazarbul\Contract\ResultStream;
use EreborCodeForge\Mazarbul\Stream\DatabaseResultStream;
use EreborCodeForge\Mazarbul\Support\Clock;
use EreborCodeForge\Mazarbul\Support\NativeSleeper;
use EreborCodeForge\Mazarbul\Support\Sleeper;
use EreborCodeForge\Mazarbul\Support\SystemClock;
use EreborCodeForge\Mazarbul\Transaction\TransactionManager;

final class Database
{
    private readonly PdoQueryExecutor $executor;
    private readonly TransactionManager $transactions;
    private ?BulkWriter $bulkWriter = null;

    public function __construct(
        private readonly ManagedConnection $connection,
        private readonly Observer $observer,
        private readonly Clock $clock = new SystemClock(),
        private readonly Sleeper $sleeper = new NativeSleeper(),
    ) {
        $this->executor = new PdoQueryExecutor($this->connection, $this->observer, $this->clock);
        $this->transactions = new TransactionManager($this->connection, $this);
    }

    public function connection(): ManagedConnection
    {
        return $this->connection;
    }

    /**
     * @param list<mixed>|array<string, mixed> $params
     */
    public function execute(string $sql, array $params = []): int
    {
        return $this->executor->execute($sql, $params)->rowCount;
    }

    /**
     * @param list<mixed>|array<string, mixed> $params
     *
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        return $this->executor->fetchOne($sql, $params);
    }

    /**
     * @param list<mixed>|array<string, mixed> $params
     *
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->executor->fetchAll($sql, $params);
    }

    /**
     * @param list<mixed>|array<string, mixed> $params
     */
    public function scalar(string $sql, array $params = []): mixed
    {
        return $this->executor->scalar($sql, $params);
    }

    /**
     * @param list<mixed>|array<string, mixed> $params
     */
    #[\NoDiscard]
    public function stream(string $sql, array $params = []): ResultStream
    {
        return new DatabaseResultStream(
            connection: $this->connection,
            sql: $sql,
            params: $params,
            observer: $this->observer,
            clock: $this->clock,
        );
    }

    /**
     * @template T
     *
     * @param callable(self): T $callback
     *
     * @return T
     */
    #[\NoDiscard]
    public function transaction(callable $callback): mixed
    {
        return $this->transactions->run($callback);
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->connection->lastInsertId($name);
    }

    public function bulk(): BulkWriter
    {
        return $this->bulkWriter ??= new BulkWriter(
            connection: $this->connection,
            executor: $this->executor,
            observer: $this->observer,
            clock: $this->clock,
            sleeper: $this->sleeper,
        );
    }
}
