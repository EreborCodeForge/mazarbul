<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Bulk;

use EreborCodeForge\Mazarbul\Bulk\Strategy\BulkUpdateStrategy;
use EreborCodeForge\Mazarbul\Contract\Dialect;

final class BulkUpdate
{
    public function __construct(
        private readonly BulkUpdateStrategy $strategy,
    ) {
    }

    /**
     * @param list<string> $updateColumns
     * @param list<array<string, mixed>> $rows
     *
     * @return array{sql: string, params: list<mixed>}
     */
    public function build(
        Dialect $dialect,
        string $table,
        string $keyColumn,
        array $updateColumns,
        array $rows,
    ): array {
        return $this->strategy->buildUpdate(
            $dialect,
            $table,
            $keyColumn,
            $updateColumns,
            $rows,
        );
    }
}
