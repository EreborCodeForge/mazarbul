<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Driver\PostgreSql;

use EreborCodeForge\Mazarbul\Driver\Support\StreamConfigurator;
use PDO;

/**
 * PostgreSQL PDO fetch is typically client-buffered unless DECLARE CURSOR is used.
 * v1 documents this limitation and relies on incremental fetch without claiming
 * unbuffered reads. Server-side cursors can be introduced later behind this API.
 */
final class PostgreSqlStreamConfigurator implements StreamConfigurator
{
    public function configure(PDO $pdo): void
    {
        // Intentionally no-op in v1: standard PDO pgsql buffers the result set.
        // Callers still benefit from Generator-based consumption for application memory.
    }

    public function restore(PDO $pdo): void
    {
        // no-op
    }
}
