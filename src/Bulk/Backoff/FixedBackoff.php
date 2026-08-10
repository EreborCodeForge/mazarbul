<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Bulk\Backoff;

use EreborCodeForge\Mazarbul\Bulk\BackoffStrategy;

final readonly class FixedBackoff implements BackoffStrategy
{
    public function __construct(
        private float $seconds,
    ) {
    }

    public function delaySeconds(int $attempt): float
    {
        return $this->seconds;
    }
}
