<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Observability\Event;

final readonly class ConnectionReconnected
{
    public function __construct(
        public string $connectionName,
        public string $driver,
        public int $generation,
    ) {
    }
}
