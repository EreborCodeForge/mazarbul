<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Observability\Event;

final readonly class QueryExecuted
{
    public function __construct(
        public string $connectionName,
        public string $driver,
        public string $operation,
        public float $durationSeconds,
        public ?int $rowCount = null,
    ) {
    }
}
