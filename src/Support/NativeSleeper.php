<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Support;

final class NativeSleeper implements Sleeper
{
    public function sleep(float $seconds): void
    {
        if ($seconds <= 0) {
            return;
        }

        usleep((int) round($seconds * 1_000_000));
    }
}
