<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Connection;

use EreborCodeForge\Mazarbul\Contract\Connection;
use EreborCodeForge\Mazarbul\Exception\TransactionException;
use PDO;
use Throwable;

/**
 * Thin PDO adapter implementing the Connection contract without lifecycle policy.
 */
final class PdoConnection implements Connection
{
    private bool $inTransaction = false;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $name,
        private readonly string $driverName,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function driver(): string
    {
        return $this->driverName;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function isConnected(): bool
    {
        return true;
    }

    public function isInTransaction(): bool
    {
        return $this->inTransaction || $this->pdo->inTransaction();
    }

    public function beginTransaction(): void
    {
        if ($this->isInTransaction()) {
            throw TransactionException::nestedNotSupported();
        }

        try {
            $this->pdo->beginTransaction();
        } catch (Throwable $e) {
            throw TransactionException::beginFailed($e);
        }

        $this->inTransaction = true;
    }

    public function commit(): void
    {
        try {
            $this->pdo->commit();
        } catch (Throwable $e) {
            throw TransactionException::commitFailed($e);
        }

        $this->inTransaction = false;
    }

    public function rollBack(): void
    {
        try {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
        } catch (Throwable $e) {
            throw TransactionException::rollbackFailed($e);
        }

        $this->inTransaction = false;
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->pdo->lastInsertId($name);
    }

    public function ping(): bool
    {
        try {
            $this->pdo->query('SELECT 1');

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
