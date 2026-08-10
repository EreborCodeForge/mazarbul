<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Support;

interface Sleeper
{
    public function sleep(float $seconds): void;
}
