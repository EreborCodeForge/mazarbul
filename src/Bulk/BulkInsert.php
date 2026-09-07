<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Bulk;

use EreborCodeForge\Mazarbul\Contract\Dialect;

final class BulkInsert
{
    /**
     * @param list<string> $columns
     * @param list<array<int|string, mixed>> $rows
     *
     * @return array{sql: string, params: list<mixed>}
     */
    public function build(Dialect $dialect, string $table, array $columns, array $rows): array
    {
        $quotedTable = $dialect->quoteIdentifier($table);
        $quotedColumns = [];
        foreach ($columns as $column) {
            $quotedColumns[] = $dialect->quoteIdentifier($column);
        }

        $columnCount = count($columns);
        $rowTemplate = '(' . implode(', ', array_fill(0, $columnCount, '?')) . ')';
        $rowCount = count($rows);
        $valueSql = $rowCount === 0
            ? ''
            : implode(', ', array_fill(0, $rowCount, $rowTemplate));

        $params = [];
        foreach ($rows as $row) {
            foreach ($columns as $index => $column) {
                $params[] = is_array($row)
                    ? ($row[$column] ?? $row[$index] ?? null)
                    : null;
            }
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $quotedTable,
            implode(', ', $quotedColumns),
            $valueSql,
        );

        return ['sql' => $sql, 'params' => $params];
    }
}
