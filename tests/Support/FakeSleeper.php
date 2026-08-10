<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Tests\Support;

use EreborCodeForge\Mazarbul\Support\Sleeper;

final class FakeSleeper implements Sleeper
{
    /** @var list<float> */
    public private(set) array $sleeps = [];

    public function sleep(float $seconds): void
    {
        $this->sleeps[] = $seconds;
    }
}
