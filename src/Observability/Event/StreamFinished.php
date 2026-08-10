<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Observability\Event;

final readonly class StreamFinished
{
    public function __construct(
        public string $connectionName,
        public string $driver,
        public int $rowsStreamed,
        public float $durationSeconds,
    ) {
    }
}
