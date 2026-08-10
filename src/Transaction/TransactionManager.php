<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Transaction;

use EreborCodeForge\Mazarbul\Connection\ManagedConnection;
use EreborCodeForge\Mazarbul\Query\Database;
use Throwable;

final class TransactionManager
{
    public function __construct(
        private readonly ManagedConnection $connection,
        private readonly Database $database,
    ) {
    }

    /**
     * @template T
     *
     * @param callable(Database): T $callback
     *
     * @return T
     */
    public function run(callable $callback): mixed
    {
        $this->connection->beginTransaction();

        try {
            $result = $callback($this->database);
            $this->connection->commit();

            return $result;
        } catch (Throwable $e) {
            try {
                $this->connection->rollBack();
            } catch (Throwable) {
                // preserve original exception
            }

            throw $e;
        }
    }
}
