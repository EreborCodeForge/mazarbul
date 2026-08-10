<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Pipeline\Sink;

use EreborCodeForge\Mazarbul\Bulk\BulkOptions;
use EreborCodeForge\Mazarbul\Contract\Sink;
use EreborCodeForge\Mazarbul\Query\Database;

/**
 * @implements Sink<int>
 */
final class BatchUpdateSink implements Sink
{
    /**
     * @param list<string> $updateColumns
     */
    public function __construct(
        private readonly Database $database,
        private readonly string $table,
        private readonly string $keyColumn,
        private readonly array $updateColumns,
        private readonly BulkOptions $options = new BulkOptions(),
    ) {
    }

    public function consume(iterable $input): mixed
    {
        /** @var iterable<array<string, mixed>> $input */
        return $this->database->bulk()->update(
            table: $this->table,
            keyColumn: $this->keyColumn,
            updateColumns: $this->updateColumns,
            rows: $input,
            options: $this->options,
        );
    }
}
