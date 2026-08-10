<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Pipeline\Sink;

use EreborCodeForge\Mazarbul\Bulk\BulkOptions;
use EreborCodeForge\Mazarbul\Contract\Sink;
use EreborCodeForge\Mazarbul\Query\Database;

/**
 * @implements Sink<int>
 */
final class BatchInsertSink implements Sink
{
    /**
     * @param list<string> $columns
     */
    public function __construct(
        private readonly Database $database,
        private readonly string $table,
        private readonly array $columns,
        private readonly BulkOptions $options = new BulkOptions(),
    ) {
    }

    public function consume(iterable $input): mixed
    {
        /** @var iterable<array<int|string, mixed>> $rows */
        $rows = $input;

        return $this->database->bulk()->insert(
            table: $this->table,
            columns: $this->columns,
            rows: $rows,
            options: $this->options,
        );
    }
}
