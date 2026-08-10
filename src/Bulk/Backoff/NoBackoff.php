<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Bulk\Backoff;

use EreborCodeForge\Mazarbul\Bulk\BackoffStrategy;

final class NoBackoff implements BackoffStrategy
{
    public function delaySeconds(int $attempt): float
    {
        return 0.0;
    }
}
