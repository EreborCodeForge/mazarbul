<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Driver;

final readonly class DriverCapabilities
{
    public function __construct(
        public bool $supportsUnbufferedReads,
        public bool $supportsServerSideCursor,
        public bool $supportsReturning,
        public int $maxBindParameters,
    ) {
    }
}
