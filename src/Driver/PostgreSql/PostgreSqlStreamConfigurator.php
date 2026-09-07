<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Driver\PostgreSql;

use EreborCodeForge\Mazarbul\Driver\Support\StreamConfigurator;
use PDO;

/**
 * PostgreSQL streaming uses DECLARE CURSOR / FETCH in DatabaseResultStream
 * when DriverCapabilities::supportsServerSideCursor is true.
 * Attribute configuration is not required for that path.
 */
final class PostgreSqlStreamConfigurator implements StreamConfigurator
{
    public function configure(PDO $pdo): void
    {
        // Cursor lifecycle is owned by PostgreSqlCursorReader.
    }

    public function restore(PDO $pdo): void
    {
        // no-op
    }
}
