<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Support;

final class SystemClock implements Clock
{
    public function now(): float
    {
        return microtime(true);
    }
}
