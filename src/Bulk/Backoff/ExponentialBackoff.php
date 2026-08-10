<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Bulk\Backoff;

use EreborCodeForge\Mazarbul\Bulk\BackoffStrategy;

final readonly class ExponentialBackoff implements BackoffStrategy
{
    public function __construct(
        private float $baseSeconds = 0.05,
        private float $multiplier = 2.0,
        private float $maxSeconds = 2.0,
        private bool $jitter = true,
    ) {
    }

    public function delaySeconds(int $attempt): float
    {
        $delay = min(
            $this->maxSeconds,
            $this->baseSeconds * ($this->multiplier ** max(0, $attempt - 1)),
        );

        if ($this->jitter && $delay > 0) {
            $delay = $delay * (0.5 + (mt_rand() / mt_getrandmax()) * 0.5);
        }

        return $delay;
    }
}
