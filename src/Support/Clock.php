<?php

declare(strict_types=1);

namespace EreborCodeForge\Mazarbul\Support;

interface Clock
{
    public function now(): float;
}
