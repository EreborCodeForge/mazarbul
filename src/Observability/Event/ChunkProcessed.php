<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Observability\Event;

final readonly class ChunkProcessed
{
    public function __construct(
        public string $connectionName,
        public string $operation,
        public int $chunkIndex,
        public int $rowCount,
    ) {
    }
}
