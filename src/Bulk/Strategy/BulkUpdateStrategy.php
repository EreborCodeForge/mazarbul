<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Bulk\Strategy;

use EreborCodeForge\Mazarbul\Contract\Dialect;

interface BulkUpdateStrategy
{
    /**
     * @param list<string> $updateColumns
     * @param list<array<string, mixed>> $rows
     *
     * @return array{sql: string, params: list<mixed>}
     */
    public function buildUpdate(
        Dialect $dialect,
        string $table,
        string $keyColumn,
        array $updateColumns,
        array $rows,
    ): array;
}
