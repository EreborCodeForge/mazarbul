<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Tests\Support;

use EreborCodeForge\Mazarbul\Support\Clock;

final class FakeClock implements Clock
{
    public function __construct(
        private float $now = 1_000.0,
    ) {
    }

    public function now(): float
    {
        return $this->now;
    }

    public function advance(float $seconds): void
    {
        $this->now += $seconds;
    }

    public function set(float $now): void
    {
        $this->now = $now;
    }
}
