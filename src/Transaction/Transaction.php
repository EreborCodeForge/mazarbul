<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Transaction;

use EreborCodeForge\Mazarbul\Connection\ManagedConnection;

final class Transaction
{
    public function __construct(
        private readonly ManagedConnection $connection,
    ) {
    }

    public function connection(): ManagedConnection
    {
        return $this->connection;
    }

    public function commit(): void
    {
        $this->connection->commit();
    }

    public function rollBack(): void
    {
        $this->connection->rollBack();
    }
}
