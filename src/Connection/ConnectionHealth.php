<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Connection;

final readonly class ConnectionHealth
{
    public function __construct(
        public bool $alive,
        public ?float $checkedAt = null,
        public ?string $message = null,
    ) {
    }
}
