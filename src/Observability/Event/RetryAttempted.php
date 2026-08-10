<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Observability\Event;

final readonly class RetryAttempted
{
    public function __construct(
        public string $connectionName,
        public string $operation,
        public int $attempt,
        public int $maxAttempts,
        public float $delaySeconds,
        public string $errorClass,
    ) {
    }
}
