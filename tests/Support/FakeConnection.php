<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Tests\Support;

use EreborCodeForge\Mazarbul\Contract\Connection;
use PDO;

/**
 * Minimal Connection double for isolated unit tests that do not need lifecycle.
 */
final class FakeConnection implements Connection
{
    private bool $inTransaction = false;

    public function __construct(
        private readonly PDO $pdo,
        private readonly string $name = 'fake',
        private readonly string $driverName = 'sqlite',
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
        $this->pdo->beginTransaction();
        $this->inTransaction = true;
    }

    public function commit(): void
    {
        $this->pdo->commit();
        $this->inTransaction = false;
    }

    public function rollBack(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        $this->inTransaction = false;
    }

    public function lastInsertId(?string $name = null): string|false
    {
        return $this->pdo->lastInsertId($name);
    }

    public function ping(): bool
    {
        return true;
    }
}
