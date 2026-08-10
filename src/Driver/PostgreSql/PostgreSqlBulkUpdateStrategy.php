<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Driver\PostgreSql;

use EreborCodeForge\Mazarbul\Bulk\Strategy\BulkUpdateStrategy;
use EreborCodeForge\Mazarbul\Contract\Dialect;

/**
 * Builds UPDATE ... FROM (VALUES ...) bulk updates for PostgreSQL.
 *
 * PDO parameters are cast explicitly in VALUES so PostgreSQL does not infer
 * everything as text (which breaks joins like integer = text).
 */
final class PostgreSqlBulkUpdateStrategy implements BulkUpdateStrategy
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
        $valueRows = [];

        foreach ($rows as $row) {
            $placeholders = [];
            $placeholders[] = $this->typedPlaceholder($row[$keyColumn]);
            $params[] = $row[$keyColumn];
            foreach ($updateColumns as $column) {
                $placeholders[] = $this->typedPlaceholder($row[$column] ?? null);
                $params[] = $row[$column] ?? null;
            }
            $valueRows[] = '(' . implode(', ', $placeholders) . ')';
        }

        $valueAliases = ['v_' . $keyColumn];
        foreach ($updateColumns as $column) {
            $valueAliases[] = 'v_' . $column;
        }

        $setParts = [];
        foreach ($updateColumns as $column) {
            $setParts[] = sprintf(
                '%s = v.%s',
                $dialect->quoteIdentifier($column),
                $dialect->quoteIdentifier('v_' . $column),
            );
        }

        $sql = sprintf(
            'UPDATE %s AS t SET %s FROM (VALUES %s) AS v (%s) WHERE t.%s = v.%s',
            $quotedTable,
            implode(', ', $setParts),
            implode(', ', $valueRows),
            implode(', ', array_map(
                static fn(string $alias): string => $dialect->quoteIdentifier($alias),
                $valueAliases,
            )),
            $quotedKey,
            $dialect->quoteIdentifier('v_' . $keyColumn),
        );

        return ['sql' => $sql, 'params' => $params];
    }

    private function typedPlaceholder(mixed $value): string
    {
        return match (true) {
            is_int($value) => '?::bigint',
            is_float($value) => '?::float8',
            is_bool($value) => '?::boolean',
            default => '?::text',
        };
    }
}
