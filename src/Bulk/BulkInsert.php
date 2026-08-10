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
        $quotedColumns = array_map(
            static fn(string $column): string => $dialect->quoteIdentifier($column),
            $columns,
        );

        $rowPlaceholders = [];
        $params = [];
        foreach ($rows as $row) {
            $placeholders = [];
            foreach ($columns as $index => $column) {
                $placeholders[] = '?';
                $params[] = is_array($row)
                    ? ($row[$column] ?? $row[$index] ?? null)
                    : null;
            }
            $rowPlaceholders[] = '(' . implode(', ', $placeholders) . ')';
        }

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s',
            $quotedTable,
            implode(', ', $quotedColumns),
            implode(', ', $rowPlaceholders),
        );

        return ['sql' => $sql, 'params' => $params];
    }
}
