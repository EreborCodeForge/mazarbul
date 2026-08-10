<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Observability\Event;

final readonly class QueryFailed
{
    public function __construct(
        public string $connectionName,
        public string $driver,
        public string $operation,
        public string $errorClass,
        public string $message,
    ) {
    }
}
