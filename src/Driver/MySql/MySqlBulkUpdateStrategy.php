<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Driver\MySql;

use EreborCodeForge\Mazarbul\Bulk\Strategy\BulkUpdateStrategy;
use EreborCodeForge\Mazarbul\Contract\Dialect;

/**
 * Builds CASE/WHEN bulk updates for MySQL.
 */
final class MySqlBulkUpdateStrategy implements BulkUpdateStrategy
{
    public function buildUpdate(
        Dialect $dialect,
        string $table,
        string $keyColumn,
        array $updateColumns,
        array $rows,
    ): array {
        $quotedTable = $dialect->quoteIdentifier($table);
        $quotedKey = $dialect->quoteIdentifier($keyColumn);
        $params = [];
        $setParts = [];

        foreach ($updateColumns as $column) {
            $quotedColumn = $dialect->quoteIdentifier($column);
            $cases = [];
            foreach ($rows as $row) {
                $cases[] = 'WHEN ? THEN ?';
                $params[] = $row[$keyColumn];
                $params[] = $row[$column];
            }
            $setParts[] = sprintf(
                '%s = CASE %s %s END',
                $quotedColumn,
                $quotedKey,
                implode(' ', $cases),
            );
        }

        $keyPlaceholders = [];
        foreach ($rows as $row) {
            $keyPlaceholders[] = '?';
            $params[] = $row[$keyColumn];
        }

        $sql = sprintf(
            'UPDATE %s SET %s WHERE %s IN (%s)',
            $quotedTable,
            implode(', ', $setParts),
            $quotedKey,
            implode(', ', $keyPlaceholders),
        );

        return ['sql' => $sql, 'params' => $params];
    }
}
