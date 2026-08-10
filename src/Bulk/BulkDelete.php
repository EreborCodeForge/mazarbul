<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Bulk;

use EreborCodeForge\Mazarbul\Contract\Dialect;

final class BulkDelete
{
    /**
     * @param list<mixed> $ids
     *
     * @return array{sql: string, params: list<mixed>}
     */
    public function build(Dialect $dialect, string $table, string $keyColumn, array $ids): array
    {
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $sql = sprintf(
            'DELETE FROM %s WHERE %s IN (%s)',
            $dialect->quoteIdentifier($table),
            $dialect->quoteIdentifier($keyColumn),
            $placeholders,
        );

        return ['sql' => $sql, 'params' => array_values($ids)];
    }
}
