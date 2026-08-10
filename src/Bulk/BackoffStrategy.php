<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Bulk;

interface BackoffStrategy
{
    /**
     * @param int $attempt 1-based attempt number that just failed
     */
    public function delaySeconds(int $attempt): float;
}
