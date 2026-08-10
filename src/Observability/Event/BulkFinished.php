<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Observability\Event;

final readonly class BulkFinished
{
    public function __construct(
        public string $connectionName,
        public string $operation,
        public string $table,
        public int $affectedRows,
        public float $durationSeconds,
    ) {
    }
}
